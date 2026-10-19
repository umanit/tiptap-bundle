<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\ContentMigrator;
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

        The colors and sizes CKEditor sets inline are only converted if the palette of the application is given with
        <info>--legacy-styles</info> (see <info>umanit:tiptap:palette</info>).

        Each rewritten content is saved first, under the identifier of the migration shown at the end:
        <info>--rollback=<identifier></info> puts the original contents back, except the ones edited since.
        <info>--history</info> lists the migrations. Only the sources which can be written are migrated.

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
            ->addOption('workers', 'w', $required, 'Number of Node processes in parallel', '4')
            ->addOption('examples', null, $required, 'Number of references given by outcome', '3')
            ->addOption('limit', null, $required, 'Maximum number of contents read by source (quick try)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = true === $input->getOption('force');
        $rollback = $input->getOption('rollback');
        $needsBackup = $apply || \is_string($rollback) || true === $input->getOption('history');

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

        $workers = $this->positiveInt($input->getOption('workers'));
        $examples = $this->positiveInt($input->getOption('examples'));
        $limit = null === $input->getOption('limit') ? null : $this->positiveInt($input->getOption('limit'));

        if (null === $workers || null === $examples || (null === $limit && null !== $input->getOption('limit'))) {
            $io->error('The options --workers, --examples and --limit expect a strictly positive integer.');

            return Command::FAILURE;
        }

        try {
            if (\is_string($rollback)) {
                $io->title(\sprintf('Rollback of the migration %s%s', $rollback, $apply ? '' : ' (simulation)'));
                $report = $this->migrator->rollback($rollback, $apply, $examples);
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
                    ),
                );
            }
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->renderReport($io, $report);

        if (!$apply) {
            $io->note('Simulation: nothing has been written. Run it again with --force to write the contents.');
        }

        return Command::SUCCESS;
    }

    private function migrate(InputInterface $input, MigrationOptions $options): MigrationReport
    {
        $sources = $this->registry->getSources(
            $this->stringOption($input, 'group'),
            $this->listOption($input, 'source'),
        );

        if ([] === $sources) {
            throw new \RuntimeException('No content source matches the selection.');
        }

        $migrationId = new \DateTimeImmutable()->format('YmdHis') . '-' . bin2hex(random_bytes(3));

        return $this->migrator->migrate($sources, $migrationId, $options);
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

    private function renderHistory(SymfonyStyle $io): int
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
                $this->backup?->getHistory() ?? [],
            ),
        );

        return Command::SUCCESS;
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
