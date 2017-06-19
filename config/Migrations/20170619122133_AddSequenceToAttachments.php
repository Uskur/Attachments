<?php
use Migrations\AbstractMigration;

class AddSequenceToAttachments extends AbstractMigration
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
        $table->addColumn('details', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->update();
    }
}
