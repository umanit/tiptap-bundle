<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripRunnerInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Palette\StyleInventory;

#[AsCommand(
    name: 'umanit:tiptap:palette',
    description: 'Inventories the inline colors and font sizes of existing contents to build a palette from them',
    help: <<<'HELP'
        Reads the HTML contents of the declared sources (the same as <info>umanit:tiptap:audit</info>) and counts, for
        each color, background color and font size set inline, the contents which use it. Nothing is written.

        The values are normalized: <info>#F00</info>, <info>rgb(255, 0, 0)</info> and <info>#ff0000</info> are one
        color, and a size in <info>pt</info> is converted to <info>px</info>.
        HELP,
)]
class PaletteCommand extends Command
{
    private const string FORMAT_TABLE = 'table';
    private const string FORMAT_JSON = 'json';

    private const array LABELS = [
        'color'           => 'Text colors',
        'backgroundColor' => 'Background colors',
        'fontSize'        => 'Font sizes',
    ];

    public function __construct(
        private readonly ContentSourceRegistry $registry,
        private readonly RoundTripRunnerInterface $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $required = InputOption::VALUE_REQUIRED;

        $this
            ->addOption('source', 's', $required | InputOption::VALUE_IS_ARRAY, 'Only reads this source, repeatable')
            ->addOption('group', 'g', $required, 'Only reads the sources of this group')
            ->addOption('format', null, $required, 'Format of the inventory: `table` or `json`', self::FORMAT_TABLE)
            ->addOption('workers', 'w', $required, 'Number of Node processes in parallel', '4')
            ->addOption('limit', null, $required, 'Maximum number of contents read by source (quick try)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        $workers = $this->positiveInt($input->getOption('workers'));
        $limit = null === $input->getOption('limit') ? null : $this->positiveInt($input->getOption('limit'));

        if (!\in_array($format, [self::FORMAT_TABLE, self::FORMAT_JSON], true)) {
            $io->error('Unknown format (expected: table or json).');

            return Command::FAILURE;
        }

        if (null === $workers || (null === $limit && null !== $input->getOption('limit'))) {
            $io->error('The options --workers and --limit expect a strictly positive integer.');

            return Command::FAILURE;
        }

        /** @var list<string> $names */
        $names = $input->getOption('source');
        $group = $input->getOption('group');

        try {
            $sources = $this->registry->getSources(\is_string($group) ? $group : null, $names);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $sources) {
            $io->warning('No content source matches the selection.');

            return Command::SUCCESS;
        }

        $inventory = new StyleInventory();

        try {
            foreach ($this->runner->run($this->registry->read($sources, $limit), $workers) as $result) {
                $inventory->add($result);
            }
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (self::FORMAT_JSON === $format) {
            $output->writeln(json_encode(
                $inventory->toArray(),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        }

        $io->title('Inline styles inventory');
        $io->text(\sprintf('%d contents read.', $inventory->getContentCount()));

        foreach (self::LABELS as $property => $label) {
            $values = $inventory->get($property);

            $io->section(\sprintf('%s — %d distinct values', $label, \count($values)));

            if ([] !== $values) {
                $io->table(
                    ['Value', 'Contents'],
                    array_map(
                        static fn(string|int $value, int $count): array => [(string) $value, $count],
                        array_keys($values),
                        $values,
                    ),
                );
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return int<1, max>|null `null` when the value is not a strictly positive integer
     */
    private function positiveInt(mixed $value): ?int
    {
        return \is_string($value) && 1 === preg_match('/^[1-9]\d*$/', $value) ? max(1, (int) $value) : null;
    }
}
