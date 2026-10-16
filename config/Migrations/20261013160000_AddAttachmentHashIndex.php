<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddAttachmentHashIndex extends BaseMigration
{
    /** @inheritDoc */
    public function change(): void
    {
        $table = $this->table('attachments');
        if (!$table->hasIndex(['md5'])) {
            $table->addIndex(['md5'], ['name' => 'attachments_md5'])->update();
        }
    }
}
