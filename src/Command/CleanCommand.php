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
use Umanit\TiptapBundle\Cleaning\CleaningOptions;
use Umanit\TiptapBundle\Cleaning\CleaningOutcome;
use Umanit\TiptapBundle\Cleaning\CleaningReport;
use Umanit\TiptapBundle\Cleaning\ContentCleaner;
use Umanit\TiptapBundle\Cleaning\HtmlCleaner;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\HtmlDiff;

#[AsCommand(
    name: 'umanit:tiptap:clean',
    description: 'Removes the leftovers of Word and of other editors from the contents, before they are migrated',
    help: <<<'HELP'
        Pasting from Word leaves a lot behind: fonts the site does not load, spacing and line heights by default, a
        language on every run of text, layout on every table cell, non-breaking spaces to indent, empty paragraphs…
        None of it has a visible effect, yet it keeps <info>umanit:tiptap:migrate</info> from rewriting the content.

        This command removes it from the contents of the declared sources (see <info>umanit:tiptap:audit --list</info>),
        rule by rule (<info>--list-rules</info>). <comment>Without --force nothing is written</comment>: it simulates
        and shows what it would do. A content whose text would change is never rewritten.

        Each rewritten content is saved first, under the identifier of the cleaning shown at the end:
        <info>umanit:tiptap:migrate --rollback=<identifier> --force</info> puts the original contents back.

        Colors and font sizes are not touched: they are converted by the palette (<info>umanit:tiptap:palette</info>).
        Run the audit again afterwards to see what is left.

        See <info>docs/cleaning.md</info>.
        HELP,
)]
class CleanCommand extends Command
{
    public function __construct(
        private readonly ContentSourceRegistry $registry,
        private readonly ContentCleaner $contentCleaner,
        private readonly HtmlCleaner $htmlCleaner,
        private readonly ?MigrationBackupInterface $backup = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $required = InputOption::VALUE_REQUIRED;

        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Writes the contents (otherwise, a simulation)')
            ->addOption('source', 's', $required | InputOption::VALUE_IS_ARRAY, 'Only cleans this source, repeatable')
            ->addOption('group', 'g', $required, 'Only cleans the sources of this group')
            ->addOption('rule', 'r', $required | InputOption::VALUE_IS_ARRAY, 'Only applies this rule, repeatable')
            ->addOption('skip-rule', null, $required | InputOption::VALUE_IS_ARRAY, 'Skips this rule, repeatable')
            ->addOption('list-rules', null, InputOption::VALUE_NONE, 'Lists the rules without cleaning anything')
            ->addOption('examples', null, $required, 'Number of references given by outcome', '3')
            ->addOption('limit', null, $required, 'Maximum number of contents read by source (quick try)')
            ->addOption('details', null, InputOption::VALUE_NONE, 'Shows the difference made on the examples')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (true === $input->getOption('list-rules')) {
            $io->table(
                ['Rule', 'What it removes'],
                \array_map(
                    static fn($rule): array => [$rule->getName(), $rule->getDescription()],
                    $this->htmlCleaner->getRules(),
                ),
            );

            return Command::SUCCESS;
        }

        $apply = true === $input->getOption('force');

        if ($apply && !$this->backup?->isReady()) {
            $io->error(
                null === $this->backup
                    ? 'No backup is configured: define a service `umanit_tiptap.migration.backup` implementing'
                    . ' MigrationBackupInterface (it is provided when the application uses Doctrine).'
                    : 'The backup table does not exist yet: create it with a Doctrine migration'
                    . ' (`doctrine:migrations:diff`, then `doctrine:migrations:migrate`).',
            );

            return Command::FAILURE;
        }

        $examples = $this->positiveInt($input->getOption('examples'));
        $limit = null === $input->getOption('limit') ? null : $this->positiveInt($input->getOption('limit'));

        if (null === $examples || (null === $limit && null !== $input->getOption('limit'))) {
            $io->error('The options --examples and --limit expect a strictly positive integer.');

            return Command::FAILURE;
        }

        $only = $this->listOption($input, 'rule');
        $skip = $this->listOption($input, 'skip-rule');
        $known = \array_map(static fn($rule): string => $rule->getName(), $this->htmlCleaner->getRules());
        $unknown = \array_diff([...$only, ...$skip], $known);

        if ([] !== $unknown) {
            $io->error(\sprintf(
                'Unknown rule(s): %s. Known rules: %s.',
                \implode(', ', $unknown),
                \implode(', ', $known),
            ));

            return Command::FAILURE;
        }

        $tracker = new ProgressTracker($io, $output->isDecorated());

        try {
            $sources = $this->registry->getSources(
                $this->stringOption($input, 'group'),
                $this->listOption($input, 'source'),
            );

            if ([] === $sources) {
                throw new \RuntimeException('No content source matches the selection.');
            }

            $report = $this->contentCleaner->clean(
                $sources,
                'clean-' . new \DateTimeImmutable()->format('YmdHis') . '-' . \bin2hex(\random_bytes(3)),
                new CleaningOptions($only, $skip, $apply, $limit, $examples, true === $input->getOption('details')),
                $tracker->update(...),
            );
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

    private function renderReport(SymfonyStyle $io, CleaningReport $report): void
    {
        if ([] !== $report->getReadOnlySources()) {
            $io->warning(\sprintf(
                'Sources which cannot be written, left alone: %s.',
                \implode(', ', $report->getReadOnlySources()),
            ));
        }

        foreach ($report->getSources() as $source) {
            $io->section($source);

            $rows = [];

            foreach (CleaningOutcome::cases() as $outcome) {
                if (0 < $report->count($outcome, $source)) {
                    $rows[] = [
                        $outcome->getLabel(),
                        $report->count($outcome, $source),
                        \implode(', ', $report->getExamples($source, $outcome)),
                    ];
                }
            }

            $io->table(['Outcome', 'Contents', 'Examples'], $rows);

            $rules = [];

            foreach ($report->getRules($source) as $rule => $stats) {
                $rules[] = [$rule, $stats['contents'], $stats['changes']];
            }

            if ([] !== $rules) {
                $io->table(['Rule', 'Contents', 'Changes'], $rules);
            }

            $this->renderDetails($io, $report, $source);
        }

        if (null !== $report->getCleaningId() && 0 < $report->count(CleaningOutcome::Cleaned)) {
            $io->success(\sprintf(
                '%d content(s) cleaned. To undo: umanit:tiptap:migrate --rollback=%s --force',
                $report->count(CleaningOutcome::Cleaned),
                $report->getCleaningId(),
            ));
        }
    }

    private function renderDetails(SymfonyStyle $io, CleaningReport $report, string $source): void
    {
        $diff = new HtmlDiff();

        foreach ($report->getDetails($source) as $detail) {
            $io->writeln(\sprintf(
                '<comment>%s</comment> — %s',
                OutputFormatter::escape($detail->reference),
                \implode(', ', \array_map(
                    static fn(string $rule, int $count): string => $rule . ' ×' . $count,
                    \array_keys($detail->changes),
                    $detail->changes,
                )),
            ));

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

    /**
     * @return list<string>
     */
    private function listOption(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        return \is_array($value) ? \array_values(\array_filter($value, \is_string(...))) : [];
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
        return \is_string($value) && 1 === \preg_match('/^[1-9]\d*$/', $value) ? \max(1, (int) $value) : null;
    }
}
