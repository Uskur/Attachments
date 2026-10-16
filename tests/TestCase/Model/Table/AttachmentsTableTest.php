<?php
declare(strict_types=1);

namespace Uskur\Attachments\Test\TestCase\Model\Table;

use Aws\Command;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use Exception;
use FilesystemIterator;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Laminas\Diactoros\UploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use RuntimeException;
use Uskur\Attachments\Model\Entity\Attachment;
use Uskur\Attachments\Model\Table\AttachmentsTable;
use Uskur\Attachments\Test\Fixture\AttachmentsFixture;
use Uskur\Attachments\Test\Fixture\OwnersFixture;

class AttachmentsTableTest extends TestCase
{
    private const OWNER = '33333333-3333-4333-8333-333333333333';
    private const FOREIGN_OWNER = '44444444-4444-4444-8444-444444444444';
    private const FILE = '55555555-5555-4555-8555-555555555555';
    private const FOREIGN_FILE = '66666666-6666-4666-8666-666666666666';

    public array $fixtures = [OwnersFixture::class, AttachmentsFixture::class];

    private mixed $storageConfiguration;
    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageConfiguration = Configure::read('Attachment');
        $this->storagePath = sys_get_temp_dir() . '/attachment-test-' . bin2hex(random_bytes(8));
        mkdir($this->storagePath);
        Configure::write('Attachment.path', $this->storagePath);
        Configure::write('Attachment.s3-endpoint', false);
        Configure::write('Attachment.models', [
            'Owners' => 'Owners', 'LegacyOwners' => 'Owners',
            'Attachments' => 'Uskur/Attachments.Attachments',
            'Uskur/Attachments.Attachments' => 'Uskur/Attachments.Attachments',
        ]);
        $connection = ConnectionManager::get('test');
        $connection->enableSavePoints(true);
        TableRegistry::getTableLocator()->set('Owners', new Table([
            'alias' => 'Owners', 'table' => 'attachment_test_owners', 'connection' => $connection,
        ]));
        foreach ([self::FILE => 'Owned bytes', self::FOREIGN_FILE => 'Foreign bytes'] as $bytes) {
            $hash = md5($bytes);
            mkdir($this->storagePath . '/' . substr($hash, 0, 2));
            file_put_contents($this->storagePath . '/' . substr($hash, 0, 2) . '/' . $hash, $bytes);
        }
        // Fixtures use native truncation; this transaction is committed/rolled back by storage cases.
        $connection->begin();
    }

    protected function tearDown(): void
    {
        $connection = ConnectionManager::get('test');
        if ($connection->inTransaction()) {
            $connection->rollback();
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->storagePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->storagePath);
        Configure::write('Attachment', $this->storageConfiguration);
        parent::tearDown();
    }

    private function sourceFile(string $bytes): string
    {
        $path = $this->storagePath . '/source-' . bin2hex(random_bytes(6));
        file_put_contents($path, $bytes);

        return $path;
    }

    private function storageTable(?S3Client $client = null): AttachmentsTable
    {
        $table = new AttachmentsTable(['alias' => 'Attachments', 'connection' => ConnectionManager::get('test')]);
        $table->setEntityClass(Attachment::class);
        if ($client !== null) {
            // The package has no client setter; inject the native SDK mock without production changes.
            (new ReflectionProperty($table, 's3client'))->setValue($table, $client);
            (new ReflectionProperty($table, 's3bucket'))->setValue($table, 'test-only');
        }

        return $table;
    }

    private function s3Client(MockHandler $handler): S3Client
    {
        return new S3Client([
            'version' => 'latest', 'region' => 'us-east-1', 'credentials' => false,
            'retries' => 0, 'handler' => $handler,
        ]);
    }

    private function missingObject(): S3Exception
    {
        return new S3Exception('Missing test object.', new Command('HeadObject'), ['response' => new Response(404)]);
    }

    public function testDeletionWaitsForTheOuterCommitAndRollbackPreservesBytes(): void
    {
        $table = $this->storageTable();
        $path = $table->localPath(md5('Owned bytes'));
        $this->assertNotFalse($table->delete($table->get(self::FILE)));
        $this->assertFileExists($path);
        ConnectionManager::get('test')->rollback();
        $this->assertFileExists($path);
    }

    public function testCommittedDeletionRemovesOnlyUnsharedBytes(): void
    {
        $table = $this->storageTable();
        $path = $table->localPath(md5('Owned bytes'));
        $this->assertNotFalse($table->delete($table->get(self::FILE)));
        ConnectionManager::get('test')->commit();
        $this->assertFileDoesNotExist($path);
        $this->assertFileExists($table->localPath(md5('Foreign bytes')));
    }

    public function testSharedHashSurvivesOneOwnersDeletion(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->update('attachments', ['md5' => md5('Owned bytes')], ['id' => self::FOREIGN_FILE]);
        $table = $this->storageTable();
        $table->delete($table->get(self::FILE));
        $connection->commit();
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
    }

    public function testFailedRemoteReplacementPreservesOriginalRowAndBytes(): void
    {
        $handler = new MockHandler([$this->missingObject(), new RuntimeException('Test write failure.'), new Result()]);
        $table = $this->storageTable($this->s3Client($handler));
        $file = $this->sourceFile('Replacement bytes');
        try {
            $table->replaceFile(self::FILE, $file);
            $this->fail('Storage failure must propagate.');
        } catch (Exception $exception) {
            $this->assertSame(md5('Owned bytes'), $table->get(self::FILE)->md5);
            $this->assertFileExists($table->localPath(md5('Owned bytes')));
            $this->assertFileDoesNotExist($table->localPath(md5('Replacement bytes')));
            $this->assertSame('DeleteObject', $handler->getLastCommand()->getName());
            $this->assertCount(0, $handler);
        }
    }

    public function testSuccessfulReplacementCreatesNewIdentityAndDefersOldFileCleanup(): void
    {
        $table = $this->storageTable();
        $original = $table->get(self::FILE);
        $original->setDetail('title', 'Previous title');
        $original->tags = ['cover'];
        $this->assertNotFalse($table->save($original));
        $file = $this->sourceFile('Replacement bytes');
        $replacement = $table->replaceFile(self::FILE, $file);
        $this->assertNotFalse($replacement);
        $this->assertNotSame(self::FILE, $replacement->id);
        $this->assertFalse($table->exists(['id' => self::FILE]));
        $this->assertSame('First.txt', $replacement->filename);
        $this->assertNull($replacement->details);
        $this->assertEmpty($replacement->tags);
        $this->assertSame(self::OWNER, $replacement->foreign_key);
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
        ConnectionManager::get('test')->commit();
        $this->assertSame(md5('Replacement bytes'), $table->get($replacement->id)->md5);
        $this->assertFileDoesNotExist($table->localPath(md5('Owned bytes')));
        $this->assertSame('Replacement bytes', file_get_contents($replacement->path));
    }

    public function testFailedPostCommitCleanupCanBeRetried(): void
    {
        $handler = new MockHandler([new RuntimeException('Test delete failure.')]);
        $table = $this->storageTable($this->s3Client($handler));
        $table->delete($table->get(self::FILE));
        ConnectionManager::get('test')->commit();
        $this->assertFalse($table->exists(['id' => self::FILE]));
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
        $handler->append(new Result());
        $this->assertTrue($table->removeUnreferencedFile(md5('Owned bytes')));
        $this->assertFileDoesNotExist($table->localPath(md5('Owned bytes')));
        $this->assertSame('DeleteObject', $handler->getLastCommand()->getName());
        $this->assertSame(substr(md5('Owned bytes'), 0, 2) . '/' . md5('Owned bytes'), $handler->getLastCommand()['Key']);
    }

    public function testRolledBackUploadIsRecoverableByDryRunThenExplicitCleanup(): void
    {
        $table = $this->storageTable();
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::OWNER);
        $file = $this->sourceFile('Rolled back bytes');
        $attachment = $table->addFile($owner, $file);
        $this->assertNotFalse($attachment);
        $path = $table->localPath(md5('Rolled back bytes'));
        ConnectionManager::get('test')->rollback();
        $this->assertFalse($table->exists(['id' => $attachment->id]));
        $this->assertFileExists($path);
        touch($path, time() - 7200);
        $statistics = $table->cleanupStorage(false);
        $this->assertSame(1, $statistics['candidates']);
        $this->assertFileExists($path);
        $statistics = $table->cleanupStorage(true);
        $this->assertSame(0, $statistics['failed']);
        $this->assertFileDoesNotExist($path);
    }

    public function testNestedRollbackCallbackDoesNotDeleteRestoredBytes(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->begin();
        $table = $this->storageTable();
        $table->delete($table->get(self::FILE));
        $connection->rollback(false);
        $connection->commit();
        $this->assertTrue($table->exists(['id' => self::FILE]));
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
    }

    public function testFailedRemoteUploadRemovesUnreferencedNewBytes(): void
    {
        $handler = new MockHandler([$this->missingObject(), new RuntimeException('Test write failure.'), new Result()]);
        $table = $this->storageTable($this->s3Client($handler));
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::OWNER);
        $file = $this->sourceFile('Failed upload');
        try {
            $table->addFile($owner, $file);
            $this->fail('Storage failure must propagate.');
        } catch (Exception $exception) {
            $this->assertFalse($table->exists(['md5' => md5('Failed upload')]));
            $this->assertFileDoesNotExist($table->localPath(md5('Failed upload')));
            $this->assertFileExists($file);
            $this->assertSame('DeleteObject', $handler->getLastCommand()->getName());
            $this->assertCount(0, $handler);
        }
    }

    public function testRemoteOrphanCleanupIsDryRunAndKeepsReferencedAndUnrelatedObjects(): void
    {
        $ownKey = substr(md5('Owned bytes'), 0, 2) . '/' . md5('Owned bytes');
        $orphanKey = substr(md5('Orphan remote'), 0, 2) . '/' . md5('Orphan remote');
        $listing = new Result(['Contents' => array_map(static fn($key) => [
            'Key' => $key, 'LastModified' => new DateTimeImmutable('-2 hours'),
        ], [$ownKey, $orphanKey, 'unrelated/object.txt'])]);
        $handler = new MockHandler([$listing, $listing, new Result()]);
        $table = $this->storageTable($this->s3Client($handler));
        $statistics = $table->cleanupStorage(false);
        $this->assertSame(1, $statistics['candidates']);
        $this->assertSame(0, $statistics['attempted']);
        $this->assertSame('ListObjectsV2', $handler->getLastCommand()->getName());
        $statistics = $table->cleanupStorage(true);
        $this->assertSame(1, $statistics['attempted']);
        $this->assertSame(0, $statistics['failed']);
        $this->assertSame('DeleteObject', $handler->getLastCommand()->getName());
        $this->assertSame($orphanKey, $handler->getLastCommand()['Key']);
        $this->assertCount(0, $handler);
    }

    public function testOwnerFieldsCannotBeReassignedAndMalformedHashCannotResolveAPath(): void
    {
        $table = $this->storageTable();
        $attachment = $table->get(self::FILE);
        $attachment->foreign_key = self::FOREIGN_OWNER;
        $this->assertFalse($table->save($attachment));
        $attachment = $table->get(self::FILE);
        $attachment->md5 = md5('Foreign bytes');
        $this->assertFalse($table->save($attachment));
        $attachment->md5 = '../../outside';
        $this->assertFalse($attachment->path);
        $this->assertNull($attachment->s3_path);
    }

    public function testUploadInspectsBytesAndRemovesStagingFile(): void
    {
        $table = $this->storageTable();
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::OWNER);
        $upload = new UploadedFile($this->sourceFile('Uploaded plain text'), 999999, UPLOAD_ERR_OK, '../file.txt', 'image/png');
        $temporaryFiles = glob(TMP . 'attachment-upload-*');
        $attachment = $table->addUpload($owner, $upload, ['text/plain']);
        $this->assertNotFalse($attachment);
        $this->assertSame('Owners', $attachment->model);
        $this->assertSame(self::OWNER, $attachment->foreign_key);
        $this->assertSame('file.txt', $attachment->filename);
        $this->assertSame(strlen('Uploaded plain text'), $attachment->size);
        $this->assertSame('text/plain', $attachment->filetype);
        $this->assertSame(md5('Uploaded plain text'), $attachment->md5);
        $this->assertSame('Uploaded plain text', file_get_contents($attachment->path));
        $this->assertSame($temporaryFiles, glob(TMP . 'attachment-upload-*'));
    }

    public function testRejectedMimeLeavesNoRowOrStagingFile(): void
    {
        $table = $this->storageTable();
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::OWNER);
        $upload = new UploadedFile($this->sourceFile('Rejected plain text'), 19, UPLOAD_ERR_OK, 'fake.png', 'image/png');
        $count = $table->find()->count();
        $temporaryFiles = glob(TMP . 'attachment-upload-*');
        try {
            $table->addUpload($owner, $upload, ['image/png']);
            $this->fail('The actual MIME must be checked.');
        } catch (Exception $exception) {
            $this->assertSame('File type not allowed.', $exception->getMessage());
        }
        $this->assertSame($count, $table->find()->count());
        $this->assertSame($temporaryFiles, glob(TMP . 'attachment-upload-*'));
    }

    public function testCopiedAttachmentGetsNewIdentityAndKeepsSharedBytes(): void
    {
        $table = $this->storageTable();
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::FOREIGN_OWNER);
        $copy = $table->copyAttachment(self::FILE, $owner);
        $this->assertNotFalse($copy);
        $this->assertNotSame(self::FILE, $copy->id);
        $this->assertSame(2, $table->get($copy->id)->sequence);
        $this->assertSame(self::FOREIGN_OWNER, $copy->foreign_key);
        $this->assertSame(md5('Owned bytes'), $copy->md5);
        $this->assertTrue($table->delete($table->get(self::FILE)));
        ConnectionManager::get('test')->commit();
        $this->assertFileExists($copy->path);
        $this->assertSame('Owned bytes', file_get_contents($copy->path));
    }

    public function testCopiedAttachmentCanBeInsertedAtTheRequestedPosition(): void
    {
        $table = $this->storageTable();
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::FOREIGN_OWNER);
        $copy = $table->copyAttachment(self::FILE, $owner, 1);
        $this->assertNotFalse($copy);
        $this->assertSame(1, $table->get($copy->id)->sequence);
        $this->assertSame(2, $table->get(self::FOREIGN_FILE)->sequence);
        $this->assertSame(1, $table->get(self::FILE)->sequence);
        $this->assertSame(self::FOREIGN_OWNER, $copy->foreign_key);
    }

    public function testCopiedAttachmentRejectsPositionsOutsideTheDestination(): void
    {
        $table = $this->storageTable();
        $owner = TableRegistry::getTableLocator()->get('Owners')->get(self::FOREIGN_OWNER);
        foreach ([-1, 0, 999] as $sequence) {
            $this->assertFalse($table->copyAttachment(self::FILE, $owner, $sequence));
            $this->assertSame(2, $table->find()->count());
            $this->assertSame(1, $table->get(self::FILE)->sequence);
            $this->assertSame(1, $table->get(self::FOREIGN_FILE)->sequence);
        }
    }

    public function testUnregisteredAndMissingOwnersCannotBeSaved(): void
    {
        $table = $this->storageTable();
        foreach (['Unregistered' => self::OWNER, 'Owners' => '77777777-7777-4777-8777-777777777777'] as $model => $owner) {
            $attachment = $table->newEntity([
                'filename' => 'Invalid.txt', 'model' => $model, 'foreign_key' => $owner,
                'md5' => md5('Invalid'), 'size' => 7, 'filetype' => 'text/plain',
            ], ['accessibleFields' => ['model' => true, 'foreign_key' => true, 'md5' => true, 'size' => true, 'filetype' => true]]);
            $this->assertFalse($table->save($attachment));
            $this->assertNotEmpty($attachment->getError('foreign_key'));
        }
    }

    public function testInvalidSequenceCannotBeSaved(): void
    {
        $table = $this->storageTable();
        foreach ([0, 999] as $sequence) {
            $attachment = $table->get(self::FILE);
            $attachment->sequence = $sequence;
            $this->assertFalse($table->save($attachment));
            $this->assertSame(1, $table->get(self::FILE)->sequence);
        }
    }

    public function testCleanupRejectsAnInsufficientGracePeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storageTable()->cleanupStorage(true, 299);
    }

    public function testBehaviorAppendsAttachmentOnlyAfterParentValidation(): void
    {
        $table = $this->storageTable();
        TableRegistry::getTableLocator()->set('Uskur/Attachments.Attachments', $table);
        $owners = TableRegistry::getTableLocator()->get('Owners');
        $owners->getValidator()->notEmptyString('name');
        $owners->addBehavior('Uskur/Attachments.Attachments', [
            'formFieldName' => 'image', 'allowedTypes' => ['text/plain'],
        ]);
        $upload = new UploadedFile($this->sourceFile('New cover bytes'), 15, UPLOAD_ERR_OK, 'cover.txt', 'text/plain');
        $owner = $owners->patchEntity($owners->get(self::OWNER), ['name' => '', 'image' => $upload]);
        $this->assertFalse($owners->save($owner));
        $this->assertSame(md5('Owned bytes'), $table->get(self::FILE)->md5);
        $this->assertFileExists($table->localPath(md5('Owned bytes')));

        $upload = new UploadedFile($this->sourceFile('New cover bytes'), 15, UPLOAD_ERR_OK, 'cover.txt', 'text/plain');
        $owner = $owners->patchEntity($owners->get(self::OWNER), ['name' => 'Edited', 'image' => $upload]);
        $this->assertNotFalse($owners->save($owner));
        $attachment = $table->find()->where(['md5' => md5('New cover bytes')])->firstOrFail();
        $this->assertNotSame(self::FILE, $attachment->id);
        $this->assertSame('cover.txt', $attachment->filename);
        $this->assertSame(2, $attachment->sequence);
        $this->assertSame(md5('Owned bytes'), $table->get(self::FILE)->md5);
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
        ConnectionManager::get('test')->commit();
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
    }

    public function testBehaviorUploadFailureRollsBackTheParent(): void
    {
        $table = $this->storageTable();
        TableRegistry::getTableLocator()->set('Uskur/Attachments.Attachments', $table);
        $owners = TableRegistry::getTableLocator()->get('Owners');
        $owners->addBehavior('Uskur/Attachments.Attachments', [
            'formFieldName' => 'image', 'allowedTypes' => ['image/png'],
        ]);
        $upload = new UploadedFile($this->sourceFile('Invalid image'), 13, UPLOAD_ERR_OK, 'fake.png', 'image/png');
        $owner = $owners->patchEntity($owners->get(self::OWNER), ['name' => 'Should roll back', 'image' => $upload]);
        try {
            $owners->save($owner);
            $this->fail('The upload failure must propagate.');
        } catch (Exception $exception) {
            $this->assertSame('File type not allowed.', $exception->getMessage());
        }
        $this->assertSame('First owner', $owners->get(self::OWNER)->name);
        $this->assertSame(md5('Owned bytes'), $table->get(self::FILE)->md5);
        $this->assertFileExists($table->localPath(md5('Owned bytes')));
    }
}
