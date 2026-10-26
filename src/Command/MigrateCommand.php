<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\ContentMigrator;
use Umanit\TiptapBundle\Migration\HtmlDiff;
use Umanit\TiptapBundle\Migration\MigrationOptions;
use Umanit\TiptapBundle\Migration\MigrationOutcome;
use Umanit\TiptapBundle\Migration\MigrationReport;

#[AsCommand(
    name: 'umanit:tiptap:migrate',
    description: 'Rewrites the contents written with another editor as the TipTap editor writes them',
    help: <<<'HELP'
        Runs each content of the declared sources (see <info>umanit:tiptap:audit --list</info>) through the schema of
        the TipTap editor and rewrites it. <comment>Without --force nothing is written</comment>: the command simulates
        and shows what it would do.

        A content is rewritten only if that loses neither text nor formatting (the one which has no visible effect
        aside). The others are left as they are, and counted: <info>--accept=indent</info> accepts the loss of a
        category of the audit, <info>--accept=decision</info> the one of a whole family.

        <info>--details</info> shows, for the contents given as examples (<info>--examples</info>) which are not
        migrated, what is lost and the difference between the HTML read and the one the editor would write.

        The colors and sizes CKEditor sets inline are only converted if the palette of the application is given with
        <info>--legacy-styles</info> (see <info>umanit:tiptap:palette</info>).

        Each rewritten content is saved first, under the identifier of the migration shown at the end:
        <info>--rollback=<identifier></info> puts the original contents back, except the ones edited since.
        <info>--history</info> lists the migrations. Only the sources which can be written are migrated.

        <info>--purge=<identifier></info> or <info>--purge-older-than=<days></info> deletes what was saved for a
        migration (or for the ones older than that many days) so that the backup does not grow forever. The migration
        can then no longer be rolled back: do it once the result is accepted. Without --force, it only lists what it
        would delete.

        See <info>docs/migration.md</info>.
        HELP,
)]
class MigrateCommand extends Command
{
    public function __construct(
        private readonly ContentSourceRegistry $registry,
        private readonly ContentMigrator $migrator,
        private readonly ?MigrationBackupInterface $backup = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $required = InputOption::VALUE_REQUIRED;

        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Writes the contents (otherwise, a simulation)')
            ->addOption('source', 's', $required | InputOption::VALUE_IS_ARRAY, 'Only migrates this source, repeatable')
            ->addOption('group', 'g', $required, 'Only migrates the sources of this group')
            ->addOption('legacy-styles', null, $required, 'Palette of the application (`legacy-styles.json`)')
            ->addOption('accept', null, $required | InputOption::VALUE_IS_ARRAY, 'Category or status of loss accepted')
            ->addOption('rollback', null, $required, 'Undoes the migration with this identifier')
            ->addOption('history', null, InputOption::VALUE_NONE, 'Lists the migrations which have been saved')
            ->addOption('purge', null, $required, 'Deletes the backup of the migration with this identifier')
            ->addOption('purge-older-than', null, $required, 'Deletes the backup of the migrations older than N days')
            ->addOption('workers', 'w', $required, 'Number of Node processes in parallel', '4')
            ->addOption('examples', null, $required, 'Number of references given by outcome', '3')
            ->addOption('limit', null, $required, 'Maximum number of contents read by source (quick try)')
            ->addOption('details', null, InputOption::VALUE_NONE, 'Shows what blocks the contents given as examples')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = true === $input->getOption('force');
        $rollback = $input->getOption('rollback');
        $purge = $this->stringOption($input, 'purge');
        $purgeOlderThan = $this->stringOption($input, 'purge-older-than');
        $purging = null !== $purge || null !== $purgeOlderThan;
        $needsBackup = $apply || \is_string($rollback) || true === $input->getOption('history') || $purging;

        if ($purging && (null !== $rollback || true === $input->getOption('history'))) {
            $io->error('--purge and --purge-older-than cannot be used with --rollback or --history.');

            return Command::FAILURE;
        }

        if (null !== $purge && null !== $purgeOlderThan) {
            $io->error('Choose between --purge and --purge-older-than.');

            return Command::FAILURE;
        }

        if ($needsBackup && !$this->backup?->isReady()) {
            $io->error(
                null === $this->backup
                    ? 'No backup is configured: define a service `umanit_tiptap.migration.backup` implementing'
                    . ' MigrationBackupInterface (it is provided when the application uses Doctrine).'
                    : 'The backup table does not exist yet: create it with a Doctrine migration'
                    . ' (`doctrine:migrations:diff`, then `doctrine:migrations:migrate`).',
            );

            return Command::FAILURE;
        }

        if (true === $input->getOption('history')) {
            return $this->renderHistory($io);
        }

        if ($purging) {
            return $this->purge($io, $apply, $purge, $purgeOlderThan);
        }

        $workers = $this->positiveInt($input->getOption('workers'));
        $examples = $this->positiveInt($input->getOption('examples'));
        $limit = null === $input->getOption('limit') ? null : $this->positiveInt($input->getOption('limit'));

        if (null === $workers || null === $examples || (null === $limit && null !== $input->getOption('limit'))) {
            $io->error('The options --workers, --examples and --limit expect a strictly positive integer.');

            return Command::FAILURE;
        }

        // Out of a terminal (redirection, CI), a bar would write a line by redraw
        $tracker = new ProgressTracker($io, $output->isDecorated());

        try {
            if (\is_string($rollback)) {
                $io->title(\sprintf('Rollback of the migration %s%s', $rollback, $apply ? '' : ' (simulation)'));
                $report = $this->migrator->rollback($rollback, $apply, $examples, $tracker->update(...));
            } else {
                $report = $this->migrate(
                    $input,
                    new MigrationOptions(
                        $workers,
                        $this->stringOption($input, 'legacy-styles'),
                        $this->listOption($input, 'accept'),
                        $apply,
                        $limit,
                        $examples,
                        true === $input->getOption('details'),
                    ),
                    $tracker->update(...),
                );
            }
        } catch (\Throwable $exception) {
            $tracker->clear();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $tracker->finish();

        $this->renderReport($io, $report);

        if (!$apply) {
            $io->note('Simulation: nothing has been written. Run it again with --force to write the contents.');
        }

        return Command::SUCCESS;
    }

    private function migrate(InputInterface $input, MigrationOptions $options, ?\Closure $onProgress): MigrationReport
    {
        $sources = $this->registry->getSources(
            $this->stringOption($input, 'group'),
            $this->listOption($input, 'source'),
        );

        if ([] === $sources) {
            throw new \RuntimeException('No content source matches the selection.');
        }

        $migrationId = new \DateTimeImmutable()->format('YmdHis') . '-' . bin2hex(random_bytes(3));

        return $this->migrator->migrate($sources, $migrationId, $options, $onProgress);
    }

    private function renderReport(SymfonyStyle $io, MigrationReport $report): void
    {
        if ([] !== $report->getReadOnlySources()) {
            $io->warning(
                \sprintf(
                    'Sources which cannot be written, left alone: %s.',
                    implode(', ', $report->getReadOnlySources()),
                ),
            );
        }

        foreach ($report->getSources() as $source) {
            $io->section($source);

            $rows = [];

            foreach (MigrationOutcome::cases() as $outcome) {
                if (0 < $report->count($outcome, $source)) {
                    $rows[] = [
                        $outcome->getLabel(),
                        $report->count($outcome, $source),
                        implode(', ', $report->getExamples($source, $outcome)),
                    ];
                }
            }

            $io->table(['Outcome', 'Contents', 'Examples'], $rows);

            $this->renderDetails($io, $report, $source);

            foreach ($report->getBlockedCategories($source) as $category => $count) {
                $io->text(
                    \sprintf(
                        '  blocked by "%s": %d (<info>--accept=%s</info> to migrate them)',
                        $category,
                        $count,
                        $category,
                    ),
                );
            }
        }

        if (null !== $report->getMigrationId() && 0 < $report->count(MigrationOutcome::Migrated)) {
            $io->success(
                \sprintf(
                    '%d content(s) migrated. To undo: --rollback=%s',
                    $report->count(MigrationOutcome::Migrated),
                    $report->getMigrationId(),
                ),
            );
        }
    }

    private function renderDetails(SymfonyStyle $io, MigrationReport $report, string $source): void
    {
        $diff = new HtmlDiff();

        foreach ($report->getDetails($source) as $detail) {
            $io->writeln(
                \sprintf(
                    '<comment>%s</comment> — %s',
                    OutputFormatter::escape($detail->reference),
                    $detail->outcome->getLabel(),
                ),
            );

            foreach ($detail->reasons as $reason) {
                $io->writeln('  ' . OutputFormatter::escape($reason));
            }

            if (null === $detail->after) {
                $io->newLine();

                continue;
            }

            foreach ($diff->diff($detail->before, $detail->after) as [$marker, $line]) {
                $text = OutputFormatter::escape($marker . ' ' . $line);

                $io->writeln(
                    match ($marker) {
                        '-' => '    <fg=red>' . $text . '</>',
                        '+' => '    <fg=green>' . $text . '</>',
                        default => '    ' . $text,
                    },
                );
            }

            $io->newLine();
        }
    }

    private function renderHistory(SymfonyStyle $io): int
    {
        $this->renderMigrations($io, $this->backup?->getHistory() ?? []);

        return Command::SUCCESS;
    }

    /**
     * @param list<array{migration_id: string, contents: int, restored: int, migrated_at: string}> $migrations
     */
    private function renderMigrations(SymfonyStyle $io, array $migrations): void
    {
        $io->table(
            ['Migration', 'Date', 'Contents saved', 'Of which restored'],
            array_map(
                static fn(array $row): array => [
                    $row['migration_id'],
                    $row['migrated_at'],
                    $row['contents'],
                    $row['restored'],
                ],
                $migrations,
            ),
        );
    }

    private function purge(SymfonyStyle $io, bool $apply, ?string $identifier, ?string $olderThan): int
    {
        if (null === $this->backup) {
            return Command::FAILURE;
        }

        $days = null === $olderThan ? null : $this->positiveInt($olderThan);

        if (null !== $olderThan && null === $days) {
            $io->error('The option --purge-older-than expects a strictly positive number of days.');

            return Command::FAILURE;
        }

        try {
            $migrations = $this->selectMigrationsToPurge($this->backup->getHistory(), $identifier, $days);
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $migrations) {
            $io->note(
                null === $identifier ? 'No saved migration is that old.' : 'No saved migration has this identifier.',
            );

            return null === $identifier ? Command::SUCCESS : Command::FAILURE;
        }

        $io->title('Backup of the migrations to purge' . ($apply ? '' : ' (simulation)'));
        $this->renderMigrations($io, $migrations);

        if (!$apply) {
            $io->note('Simulation: nothing has been deleted. Run it again with --force to delete these backups.');

            return Command::SUCCESS;
        }

        $purged = 0;

        foreach ($migrations as $migration) {
            $purged += $this->backup->purge($migration['migration_id']);
        }

        $io->success(
            \sprintf(
                '%d content(s) of %d migration(s) purged: they can no longer be rolled back.',
                $purged,
                \count($migrations),
            ),
        );

        return Command::SUCCESS;
    }

    /**
     * @param list<array{migration_id: string, contents: int, restored: int, migrated_at: string}> $history
     * @param int<1, max>|null                                                                     $days
     *
     * @return list<array{migration_id: string, contents: int, restored: int, migrated_at: string}>
     *
     * @throws \Exception If the date of a migration cannot be read
     */
    private function selectMigrationsToPurge(array $history, ?string $identifier, ?int $days): array
    {
        if (null !== $identifier) {
            return array_values(
                array_filter($history, static fn(array $row): bool => $row['migration_id'] === $identifier),
            );
        }

        $limit = new \DateTimeImmutable()->modify(\sprintf('-%d days', $days ?? 0));

        return array_values(
            array_filter(
                $history,
                static fn(array $row): bool => new \DateTimeImmutable($row['migrated_at']) < $limit,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function listOption(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        return \is_array($value) ? array_values(array_filter($value, \is_string(...))) : [];
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) ? $value : null;
    }

    /**
     * @return int<1, max>|null `null` when the value is not a strictly positive integer
     */
    private function positiveInt(mixed $value): ?int
    {
        return \is_string($value) && 1 === preg_match('/^[1-9]\d*$/', $value) ? max(1, (int) $value) : null;
    }
}
