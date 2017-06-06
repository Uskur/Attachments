<?php
use Migrations\AbstractMigration;

class AlterAttachments extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('attachments');
        $table->changeColumn('filetype', 'string', [
            'default' => null,
            'limit' => 150,
            'null' => false,
        ]);
        $table->update();
    }
}
