<?php
declare(strict_types=1);

namespace Uskur\Attachments\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class OwnersFixture extends TestFixture
{
    public string $table = 'attachment_test_owners';

    public array $records = [
        ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'First owner'],
        ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Second owner'],
    ];
}
