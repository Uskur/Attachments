<?php
declare(strict_types=1);

namespace Uskur\Attachments\Test\TestCase\Model\Entity;

use Cake\TestSuite\TestCase;
use Uskur\Attachments\Model\Entity\Attachment;

class AttachmentTest extends TestCase
{
    public function testOwnerAndStorageFieldsAreNotMassAssignable(): void
    {
        $fields = ['id', 'model', 'foreign_key', 'md5', 'size', 'filetype', 'sequence', 'created', 'upload', 'tmpPath'];
        $attachment = new Attachment(array_fill_keys($fields, 'Injected') + ['filename' => 'Allowed.txt'], ['guard' => true]);
        foreach ($fields as $field) {
            $this->assertFalse($attachment->isAccessible($field));
            $this->assertNull($attachment->get($field));
        }
        $this->assertSame('Allowed.txt', $attachment->filename);
    }

    public function testMalformedHashCannotResolveStoragePaths(): void
    {
        foreach (['../../outside', '', null, strtoupper(md5('Example'))] as $hash) {
            $attachment = new Attachment(['md5' => $hash], ['guard' => false]);
            $this->assertFalse($attachment->path);
            $this->assertNull($attachment->s3_path);
        }
    }

    public function testDetailsAndTagsRoundTripAndMissingTimestampIsSafe(): void
    {
        $attachment = new Attachment(['details' => '{"title":"Example","description":"Text"}', 'tags' => ['cover', 'featured']]);
        $this->assertSame('Example', $attachment->getDetail('title'));
        $attachment->setDetail('link', '/example');
        $this->assertSame('/example', $attachment->details_array['link']);
        $this->assertSame(['cover', 'featured'], $attachment->tags);
        $this->assertSame('', $attachment->readable_created);
    }
}
