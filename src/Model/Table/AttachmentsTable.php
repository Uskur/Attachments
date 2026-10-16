<?php
declare(strict_types=1);

namespace Uskur\Attachments\Model\Table;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;
use Uskur\Attachments\Model\Entity\Attachment;

/**
 * Attachments Model
 */
class AttachmentsTable extends Table
{
    protected $s3client = false;
    protected $s3bucket = false;

    /**
     * Initialize table config, associations, and optional S3 client.
     *
     * @param array $config Table config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('attachments');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('ADmad/Sequence.Sequence', [
            'sequenceField' => 'sequence',
            'scope' => ['model', 'foreign_key'],
            'startAt' => 1,
        ]);

        $this->belongsTo('ParentAttachment', [
            'className' => 'Uskur/Attachments.Attachments',
            'foreignKey' => 'foreign_key',
            'conditions' => [$this->aliasField('model') . ' IN' => ['Attachments', 'Uskur/Attachments.Attachments']],
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('SubAttachments', [
            'className' => 'Uskur/Attachments.Attachments',
            'foreignKey' => 'foreign_key',
            'conditions' => ['SubAttachments.model IN' => ['Attachments', 'Uskur/Attachments.Attachments']],
            'dependent' => true, 'cascadeCallbacks' => true,
        ]);

        if (Configure::read('Attachment.s3-endpoint')) {
            $config = [
                'version' => 'latest',
                'region' => Configure::read('Attachment.s3-region'),
                'endpoint' => Configure::read('Attachment.s3-endpoint'),
                'credentials' => [
                    'key' => Configure::read('Attachment.s3-key'),
                    'secret' => Configure::read('Attachment.s3-secret'),
                ],
            ];
            $this->s3client = new \Aws\S3\S3Client($config);
            $this->s3bucket = Configure::read('Attachment.s3-bucket');
        }
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator->allowEmptyString('filename');
        $validator->allowEmptyString('md5');

        $validator
            ->integer('size')
            ->allowEmptyString('size');

        return $validator;
    }

    /**
     * Build application integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(function ($attachment) {
            try {
                return is_string($attachment->foreign_key) && $attachment->foreign_key !== ''
                    && $this->getOwnerTable($attachment->model)->exists(['id' => $attachment->foreign_key]);
            } catch (\InvalidArgumentException $exception) {
                return false;
            }
        }, 'registeredOwner', ['errorField' => 'foreign_key']);
        $rules->addUpdate(static fn($attachment, $options) => !$attachment->isDirty('model')
            && (!$attachment->isDirty('foreign_key') || (!empty($options['allowFolderTransfer'])
                && in_array($attachment->model, ['FileFolders', 'Uskur/WebpageManager.FileFolders'], true))),
            'immutableOwner', ['errorField' => 'foreign_key']);
        $rules->add(static fn($attachment) => (!$attachment->isNew() && !$attachment->isDirty('md5'))
            || (is_string($attachment->md5) && preg_match('/^[a-f0-9]{32}$/D', $attachment->md5)),
            'validHash', ['errorField' => 'md5']);
        $rules->addUpdate(static fn($attachment) => !$attachment->isDirty('md5') && !$attachment->isDirty('size')
            && !$attachment->isDirty('filetype'), 'managedFile', ['errorField' => 'md5']);
        $rules->addCreate(function ($attachment): bool {
            return $attachment->sequence === null || (ctype_digit((string)$attachment->sequence)
                && (int)$attachment->sequence >= 1 && (int)$attachment->sequence <= $this->find()->where([
                    'model' => $attachment->model, 'foreign_key' => $attachment->foreign_key,
                ])->count() + 1);
        }, 'validSequence', ['errorField' => 'sequence']);
        $rules->addUpdate(function ($attachment, $options) {
            return (!empty($options['allowFolderTransfer']) && $attachment->isDirty('foreign_key')
                && $attachment->sequence === null) || !$attachment->isDirty('sequence')
                || (ctype_digit((string)$attachment->sequence)
                && (int)$attachment->sequence >= 1 && (int)$attachment->sequence <= $this->find()->where([
                    'model' => $attachment->model, 'foreign_key' => $attachment->foreign_key,
                ])->count());
        }, 'validSequence', ['errorField' => 'sequence']);

        return $rules;
    }

    /** Resolve an owner through the host's registered model aliases. */
    public function getOwnerTable(mixed $model): Table
    {
        $models = Configure::read('Attachment.models', []);
        $alias = is_string($model) ? ($models[str_replace('-', '/', $model)] ?? null) : null;
        if (!$alias) {
            throw new \InvalidArgumentException('Unregistered attachment owner model.');
        }

        return TableRegistry::getTableLocator()->get($alias);
    }

    /**
     * Save one uploaded attachment.
     *
     * @param \Cake\Datasource\EntityInterface $entity Related entity.
     * @param \Psr\Http\Message\UploadedFileInterface $upload Uploaded file object.
     * @param array|null $allowed_types Allowed MIME types.
     * @param array $details Extra details.
     * @return \Uskur\Attachments\Model\Entity\Attachment|false
     */
    public function addUpload(
        EntityInterface $entity,
        UploadedFileInterface $upload,
        ?array $allowed_types = [],
        array $details = [],
    )
    {
        $allowed_types ??= [];
        $payload = $this->normalizeUpload($upload);
        if (!$payload) {
            return false;
        }

        try {
            if (!empty($allowed_types) && !in_array($payload['type'], $allowed_types, true)) {
                throw new \Exception('File type not allowed.');
            }

            $attachment = $this->newEntity([
                'model' => $entity->getSource(),
                'foreign_key' => $entity->id,
                'filename' => $payload['filename'],
                'size' => $payload['size'],
                'filetype' => $payload['type'],
                'md5' => $payload['md5'],
                'upload' => $payload['upload'] ?? null,
                'tmpPath' => $payload['tmpPath'] ?? null,
            ], ['accessibleFields' => array_fill_keys(['model', 'foreign_key', 'size', 'filetype', 'md5', 'upload', 'tmpPath'], true)]);

            if (!empty($details)) {
                $attachment->details = json_encode($details);
            }

            $existingConditions = [
                'filename' => $attachment->filename,
                'model' => $attachment->model,
                'foreign_key' => $attachment->foreign_key,
                'md5' => $attachment->md5,
            ];
            if ($attachment->details !== null && $attachment->details !== '') {
                $existingConditions['details'] = $attachment->details;
            } else {
                $existingConditions['details IS'] = null;
            }

            $existing = $this->find()->where($existingConditions)->first();
            if ($existing) {
                if (!empty($details)) {
                    $existing->details = json_encode($details);

                    return $this->save($existing);
                }

                return $existing;
            }

            try {
                $saved = $this->save($attachment);
            } catch (Throwable $exception) {
                $this->removeUnreferencedFile($attachment->md5);
                throw $exception;
            }
            if (!$saved) {
                $this->removeUnreferencedFile($attachment->md5);
            }
            return $saved;
        } finally {
            if (is_file($payload['tmpPath'])) {
                unlink($payload['tmpPath']);
            }
        }
    }

    /**
     * Save an existing file as an attachment.
     *
     * @param \Cake\Datasource\EntityInterface $entity Related entity.
     * @param string $filePath Source file path.
     * @param array $details Extra details.
     * @return \Uskur\Attachments\Model\Entity\Attachment|false
     */
    public function addFile($entity, $filePath, $details = [])
    {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $fileMime = mime_content_type($filePath);
        $fileMd5 = md5_file($filePath);
        if ($fileSize === false || $fileMime === false || $fileMd5 === false) {
            throw new \Exception("File {$filePath} could not be read.");
        }
        $attachment = $this->newEntity([
            'model' => $entity->getSource(),
            'foreign_key' => $entity->id,
            'filename' => $fileName,
            'size' => $fileSize,
            'filetype' => $fileMime,
            'md5' => $fileMd5,
            'tmpPath' => $filePath,
        ], ['accessibleFields' => array_fill_keys(['model', 'foreign_key', 'size', 'filetype', 'md5', 'upload', 'tmpPath'], true)]);

        if ($details) {
            $attachment->details = json_encode($details);
        }

        $existingConditions = [
            'filename' => $attachment->filename,
            'model' => $attachment->model,
            'foreign_key' => $attachment->foreign_key,
            'md5' => $attachment->md5,
        ];
        if ($attachment->details !== null && $attachment->details !== '') {
            $existingConditions['details'] = $attachment->details;
        } else {
            $existingConditions['details IS'] = null;
        }

        $existing = $this->find()->where($existingConditions)->first();
        if ($existing) {
            return $existing;
        }

        try {
            $saved = $this->save($attachment);
        } catch (Throwable $exception) {
            $this->removeUnreferencedFile($attachment->md5);
            throw $exception;
        }
        if (!$saved) {
            $this->removeUnreferencedFile($attachment->md5);
        }
        return $saved ? $attachment : false;
    }

    /**
     * Move the uploaded file after the attachment row is saved.
     *
     * @param \Cake\Event\EventInterface $event Event instance.
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Saved attachment.
     * @param \ArrayObject $options Save options.
     * @return void
     */
    public function afterSave(EventInterface $event, Attachment $attachment, ArrayObject $options)
    {
        if (!$attachment->tmpPath) {
            return;
        }
        $path = $this->localPath($attachment->md5);
        if (!is_file($path)) {
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                throw new RuntimeException('Attachment storage directory could not be created.');
            }
            $temporary = tempnam(dirname($path), 'attachment-');
            try {
                if ($temporary === false || !copy($attachment->tmpPath, $temporary) || !rename($temporary, $path)) {
                    throw new RuntimeException('Attachment bytes could not be stored.');
                }
            } finally {
                if ($temporary !== false && is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        if ($this->s3bucket !== false && !$this->s3client->doesObjectExistV2($this->s3bucket, $attachment->s3_path)) {
            $this->putToS3($attachment, $path);
        }
        $attachment->tmpPath = null;
    }

    /**
     * Remove orphaned files after attachment deletion.
     *
     * @param \Cake\Event\EventInterface $event Event instance.
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Deleted attachment.
     * @param \ArrayObject $options Delete options.
     * @return void
     */
    public function afterDelete(EventInterface $event, Attachment $attachment, ArrayObject $options)
    {
        if (!is_string($attachment->md5) || !preg_match('/^[a-f0-9]{32}$/D', $attachment->md5)) {
            return;
        }
        $this->getConnection()->afterCommit(function () use ($attachment): void {
            $this->removeUnreferencedFile($attachment->md5);
        });
    }

    /**
     * Fetch attachments for an article filtered by top-level MIME type.
     *
     * @param string $articleId Article ID.
     * @param string $type MIME type prefix.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function getAttachmentsOfArticle($articleId, $type = 'image')
    {
        return $this->find('all',
        conditions: [
            'Attachments.article_id' => $articleId,
            'Attachments.filetype LIKE' => "$type/%",
        ],
        contain: []);
    }

    /**
     * Replace file.
     *
     * @param string $id Attachment ID.
     * @param string $tmpPath Path to the new file.
     * @return \Uskur\Attachments\Model\Entity\Attachment|bool
     */
    public function replaceFile(string $id, string $tmpPath)
    {
        $currentAttachment = $this->get($id);
        $size = filesize($tmpPath);
        $type = mime_content_type($tmpPath);
        $hash = md5_file($tmpPath);
        if ($size === false || $type === false || $hash === false) {
            throw new RuntimeException('The replacement file could not be inspected.');
        }
        $attachment = $this->newEntity([
            'model' => $currentAttachment->model,
            'foreign_key' => $currentAttachment->foreign_key,
            'filename' => $currentAttachment->filename,
            'size' => $size, 'filetype' => $type, 'md5' => $hash, 'tmpPath' => $tmpPath,
        ], ['accessibleFields' => array_fill_keys(['model', 'foreign_key', 'size', 'filetype', 'md5', 'tmpPath'], true)]);
        try {
            $saved = $this->getConnection()->transactional(function () use ($currentAttachment, $attachment) {
                if (!$this->delete($currentAttachment)) {
                    return false;
                }

                return $this->save($attachment);
            });
        } catch (Throwable $exception) {
            $this->removeUnreferencedFile($hash);
            throw $exception;
        }
        if (!$saved) {
            $this->removeUnreferencedFile($hash);
        }

        return $saved ?: false;
    }

    /**
     * Move local attachments to S3 storage.
     *
     * @param int $limit Max number of files to move.
     * @return void
     */
    public function moveFilesToS3($limit = 100)
    {
        if ($this->s3bucket == false) {
            throw new \Exception('S3 bucket not configured');
        }
        $moved = 0;
        $attachments = $this->find();
        foreach ($attachments as $attachment) {
            if ($moved >= $limit) {
                break;
            }
            if ($attachment->path && file_exists($attachment->path)) {
                if (!$this->s3client->doesObjectExistV2($this->s3bucket, $attachment->s3_path)) {
                    $this->putToS3($attachment, $attachment->path);
                    $moved++;
                }
            }
        }
    }

    /**
     * Copies an attachment to a new entity.
     *
     * @param string $id Attachment ID.
     * @param \Cake\Datasource\EntityInterface $entity The target entity.
     * @param int|null $sequence Position for the copied attachment.
     * @return bool|\Uskur\Attachments\Model\Entity\Attachment
     */
    public function copyAttachment($id, $entity, ?int $sequence = null)
    {
        $currentAttachment = $this->get($id);
        $newAttachmentData = $currentAttachment->toArray();
        unset($newAttachmentData['id'], $newAttachmentData['created'], $newAttachmentData['sequence']);
        $newAttachmentData['model'] = $entity->getSource();
        $newAttachmentData['foreign_key'] = $entity->id;
        $newAttachmentData['sequence'] = $sequence;
        $newAttachment = $this->newEntity($newAttachmentData, ['accessibleFields' => array_fill_keys(['model', 'foreign_key', 'size', 'filetype', 'md5', 'upload', 'tmpPath', 'sequence'], true)]);

        $saved = $this->save($newAttachment);
        Log::write($saved ? 'info' : 'error', $saved ? 'Attachment copied.' : 'Attachment could not be copied.', [
            'source_id' => $id, 'id' => $newAttachment->id,
            'model' => $newAttachment->model, 'foreign_key' => $newAttachment->foreign_key,
            'errors' => $newAttachment->getErrors(),
        ]);

        return $saved ? $newAttachment : false;
    }

    /**
     * Normalize a PSR-7 uploaded file.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $upload Uploaded file input.
     * @return array|null
     */
    private function normalizeUpload(UploadedFileInterface $upload): ?array
    {
        if ($upload->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new \Exception('Upload errors.');
        }

        $path = tempnam(TMP, 'attachment-upload-');
        if ($path === false) {
            throw new RuntimeException('An upload staging file could not be created.');
        }
        try {
            $upload->moveTo($path);
            $size = filesize($path);
            $type = mime_content_type($path);
            $md5 = md5_file($path);
            if ($size === false || $type === false || $md5 === false) {
                throw new RuntimeException('The uploaded file could not be inspected.');
            }

            return ['filename' => basename((string)$upload->getClientFilename()), 'size' => $size,
                'type' => $type, 'md5' => $md5, 'tmpPath' => $path];
        } catch (Throwable $exception) {
            unlink($path);
            throw $exception;
        }
    }

    /** Local storage path without downloading remote bytes as a side effect. */
    public function localPath(string $hash): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $hash)) {
            throw new \InvalidArgumentException('Invalid attachment hash.');
        }

        return Configure::read('Attachment.path') . DS . substr($hash, 0, 2) . DS . $hash;
    }

    /** Retryable cleanup; database references always take precedence over deletion. */
    public function removeUnreferencedFile(string $hash): bool
    {
        try {
            return $this->getConnection()->transactional(function () use ($hash): bool {
                $path = $this->localPath($hash);
                $references = $this->find()->select(['id'])->where(['md5' => $hash])->epilog('FOR UPDATE')->all();
                if (!$references->isEmpty()) {
                    return true;
                }
                if ($this->s3bucket !== false) {
                    $this->s3client->deleteObject(['Bucket' => $this->s3bucket, 'Key' => substr($hash, 0, 2) . '/' . $hash]);
                }
                if (is_file($path) && !unlink($path)) {
                    throw new RuntimeException('Unreferenced attachment bytes could not be removed.');
                }
                return true;
            });
        } catch (Throwable $exception) {
            Log::write('error', 'Attachment storage cleanup failed; the file can be retried.', ['md5' => $hash, 'exception' => $exception]);
            return false;
        }
    }

    /** Find old orphaned hashes; deletion is an explicit maintenance operation. */
    public function cleanupStorage(bool $delete = false, int $minimumAge = 3600): array
    {
        if ($minimumAge < 300) {
            throw new \InvalidArgumentException('Storage cleanup requires at least a five-minute grace period.');
        }
        $cutoff = time() - $minimumAge;
        $candidates = [];
        $root = Configure::read('Attachment.path');
        if (is_dir($root)) {
            foreach (new \DirectoryIterator($root) as $directory) {
                if (!$directory->isDir() || !preg_match('/^[a-f0-9]{2}$/D', $directory->getFilename())) {
                    continue;
                }
                foreach (new \DirectoryIterator($directory->getPathname()) as $file) {
                    $hash = $file->getFilename();
                    if ($file->isFile() && preg_match('/^[a-f0-9]{32}$/D', $hash)
                        && substr($hash, 0, 2) === $directory->getFilename() && $file->getMTime() < $cutoff) {
                        $candidates[$hash] = true;
                    }
                }
            }
        }
        if ($this->s3bucket !== false) {
            $token = null;
            do {
                $arguments = ['Bucket' => $this->s3bucket];
                if ($token !== null) {
                    $arguments['ContinuationToken'] = $token;
                }
                $result = $this->s3client->listObjectsV2($arguments);
                foreach ($result['Contents'] ?? [] as $object) {
                    $key = $object['Key'];
                    if (preg_match('#^([a-f0-9]{2})/([a-f0-9]{32})$#D', $key, $match)
                        && $match[1] === substr($match[2], 0, 2) && $object['LastModified']->getTimestamp() < $cutoff) {
                        $candidates[$match[2]] = true;
                    }
                }
                $token = $result['NextContinuationToken'] ?? null;
            } while ($token !== null);
        }
        $statistics = ['candidates' => 0, 'attempted' => 0, 'failed' => 0];
        foreach (array_keys($candidates) as $hash) {
            if ($this->exists(['md5' => $hash])) {
                continue;
            }
            $statistics['candidates']++;
            if ($delete) {
                $statistics['attempted']++;
                if (!$this->removeUnreferencedFile($hash)) {
                    $statistics['failed']++;
                }
            }
        }
        // Only this package's known staging prefixes are eligible for age-based cleanup.
        $temporaryFiles = array_merge(glob(TMP . 'attachment-upload-*') ?: [], glob(TMP . 'attachment-replace-*') ?: [], glob(TMP . 'attachment-preview-*') ?: []);
        if (is_dir($root)) {
            $temporaryFiles = array_merge($temporaryFiles, glob($root . DS . '[a-f0-9][a-f0-9]' . DS . 'attachment-*') ?: []);
        }
        foreach ($temporaryFiles as $file) {
            if (!is_file($file) || filemtime($file) >= $cutoff) {
                continue;
            }
            $statistics['candidates']++;
            if ($delete) {
                $statistics['attempted']++;
                if (!unlink($file)) {
                    $statistics['failed']++;
                }
            }
        }

        return $statistics;
    }

    /**
     * Upload a local file to S3 if object storage is configured.
     *
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Attachment entity.
     * @param string $path Local file path.
     * @return void
     */
    private function putToS3(Attachment $attachment, string $path): void
    {
        try {
            $this->s3client->putObject([
                'Bucket' => $this->s3bucket,
                'Key' => $attachment->s3_path,
                'SourceFile' => $path,
            ]);
        } catch (\Exception $e) {
            throw new \Exception("File {$path} could not be moved to S3 bucket");
        }
    }
}
