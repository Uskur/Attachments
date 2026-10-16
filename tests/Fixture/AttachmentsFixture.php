<?php
declare(strict_types=1);

namespace Uskur\Attachments\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class AttachmentsFixture extends TestFixture
{
    public string $table = 'attachments';

    public array $records = [
        [
            'id' => '55555555-5555-4555-8555-555555555555',
            'model' => 'Owners', 'foreign_key' => '33333333-3333-4333-8333-333333333333',
            'filename' => 'First.txt', 'filetype' => 'text/plain', 'size' => 11,
            'md5' => '3a38b7d34d1bf36ea118abd58f7b07cd', 'sequence' => 1,
            'created' => '2026-01-01 12:00:00',
        ],
        [
            'id' => '66666666-6666-4666-8666-666666666666',
            'model' => 'Owners', 'foreign_key' => '44444444-4444-4444-8444-444444444444',
            'filename' => 'Second.txt', 'filetype' => 'text/plain', 'size' => 13,
            'md5' => 'd27cd13347a4f2bf40470ca4a529bffe', 'sequence' => 1,
            'created' => '2026-01-01 12:00:00',
        ],
    ];
}
