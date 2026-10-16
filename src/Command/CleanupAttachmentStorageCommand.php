<?php
declare(strict_types=1);

namespace Uskur\Attachments\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/** Retry failed storage deletion and remove bytes left by rolled-back uploads. */
class CleanupAttachmentStorageCommand extends Command
{
    /** @inheritDoc */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Inspect orphaned attachment storage; dry-run by default.')
            ->addOption('delete', [
                'boolean' => true, 'default' => false, 'help' => 'Delete eligible unreferenced files.',
            ])
            ->addOption('older-than', ['default' => '3600', 'help' => 'Minimum file age in seconds (at least 300).']);
    }

    /** @inheritDoc */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $statistics = $this->fetchTable('Uskur/Attachments.Attachments')->cleanupStorage(
            (bool)$args->getOption('delete'),
            (int)$args->getOption('older-than'),
        );
        $io->out(sprintf(
            'Candidates: %d; cleanup attempts: %d; failures: %d. %s',
            $statistics['candidates'],
            $statistics['attempted'],
            $statistics['failed'],
            $args->getOption('delete') ? 'Cleanup requested.' : 'Dry-run: no files changed.',
        ));

        return $statistics['failed'] ? self::CODE_ERROR : self::CODE_SUCCESS;
    }
}
