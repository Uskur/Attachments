<?php
use Migrations\AbstractMigration;

class AddSequenceIndexToAttachments extends AbstractMigration
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
        $table->addIndex(['model', 'foreign_key', 'sequence'], ['name' => 'model_foreign_key_sequence']);
        $table->update();
    }
}
