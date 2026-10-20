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
use Umanit\TiptapBundle\Palette\Palette;
use Umanit\TiptapBundle\Palette\PaletteBuilder;
use Umanit\TiptapBundle\Palette\PaletteColor;
use Umanit\TiptapBundle\Palette\PaletteRenderer;
use Umanit\TiptapBundle\Palette\PaletteSize;
use Umanit\TiptapBundle\Palette\StyleInventory;

#[AsCommand(
    name: 'umanit:tiptap:palette',
    description: 'Inventories the inline colors and font sizes of existing contents and proposes a palette',
    help: <<<'HELP'
        Reads the HTML contents of the declared sources (the same as <info>umanit:tiptap:audit</info>) and counts, for
        each color, background color and font size set inline, the contents which use it. Nothing is written.

        The values are normalized: <info>#F00</info>, <info>rgb(255, 0, 0)</info> and <info>#ff0000</info> are one
        color, and a size in <info>pt</info> is converted to <info>px</info>.

        The close colors are then grouped (<info>--max-distance</info>: the largest difference on a red, green or blue
        channel) and given generic names (<info>red-1</info>, <info>blue-2</info>…), as are the close font sizes
        (<info>--max-size-distance</info>, in pixels). With <info>--output-dir</info>, the palette is written as:

          - the colors set builder of the application (<info>--builder-class</info>, <info>--index-name</info>);
          - <info>palette.css</info>: the <info>ut-color-*</info>, <info>ut-bg-*</info> and <info>ut-fs-*</info>
            classes;
          - <info>legacy-styles.json</info>: the map of the legacy import mode, for the audit
            (<info>assets/bin/roundtrip.js --legacy-styles</info>) and for a migration.

        Rename the generic names before using the files: the classes are what ends up in the migrated contents.
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
        private readonly PaletteBuilder $builder,
        private readonly PaletteRenderer $renderer,
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
            ->addOption('max-distance', null, $required, 'Largest RGB channel difference in a color group', '24')
            ->addOption('max-size-distance', null, $required, 'Largest difference in pixels in a size group', '0')
            ->addOption('output-dir', null, $required, 'Writes the builder, the CSS and the legacy styles map there')
            ->addOption('builder-class', null, $required, 'Class of the generated builder (with --output-dir)')
            ->addOption('index-name', null, $required, 'Name of the generated colors set', 'default')
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

        $maxDistance = $this->integer($input->getOption('max-distance'), 255);
        $maxSizeDistance = $this->integer($input->getOption('max-size-distance'), 1000);

        if (null === $maxDistance || null === $maxSizeDistance) {
            $io->error('The options --max-distance (0 to 255) and --max-size-distance expect a positive integer.');

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

        // Out of a terminal (redirection, CI), a bar would write a line by redraw; the JSON goes to stdout
        $progress = self::FORMAT_TABLE === $format && $output->isDecorated() ? $io->createProgressBar() : null;
        $progress?->setFormat(' %current% contents read [%elapsed:6s%]');
        $progress?->setRedrawFrequency(50);

        try {
            foreach ($this->runner->run($this->registry->read($sources, $limit), $workers) as $result) {
                $inventory->add($result);
                $progress?->advance();
            }
        } catch (\RuntimeException $exception) {
            $progress?->clear();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (null !== $progress) {
            $progress->finish();
            $io->newLine(2);
        }

        $palette = $this->builder->build($inventory, $maxDistance, $maxSizeDistance);
        $outputDir = $input->getOption('output-dir');

        if (\is_string($outputDir) && !$this->write($io, $palette, $outputDir, $input)) {
            return Command::FAILURE;
        }

        if (self::FORMAT_JSON === $format) {
            $output->writeln(json_encode(
                $inventory->toArray() + ['palette' => $palette->toLegacyStyles(), 'unmapped' => $palette->unmapped],
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

        $this->renderPalette($io, $palette);

        return Command::SUCCESS;
    }

    private function renderPalette(SymfonyStyle $io, Palette $palette): void
    {
        $io->section(\sprintf(
            'Proposed palette — %d colors, %d sizes',
            \count($palette->colors),
            \count($palette->sizes),
        ));

        $io->table(
            ['Name', 'Color', 'Contents', 'Grouped colors'],
            array_map(
                static fn(PaletteColor $color): array => [
                    $color->name,
                    $color->hex,
                    $color->getUsage(),
                    implode(', ', array_keys($color->members)),
                ],
                $palette->colors,
            ),
        );

        $io->table(
            ['Class', 'Contents', 'Grouped sizes'],
            array_map(
                static fn(PaletteSize $size): array => [
                    $size->getClass(),
                    $size->getUsage(),
                    implode(', ', array_keys($size->members)),
                ],
                $palette->sizes,
            ),
        );

        foreach ($palette->unmapped as $property => $values) {
            $io->warning(\sprintf(
                'Not in the palette (%s): %s.',
                self::LABELS[$property] ?? $property,
                implode(', ', array_map(
                    static fn(string|int $value, int $count): string => \sprintf('%s (%d)', $value, $count),
                    array_keys($values),
                    $values,
                )),
            ));
        }
    }

    private function write(SymfonyStyle $io, Palette $palette, string $directory, InputInterface $input): bool
    {
        $class = $input->getOption('builder-class');
        $index = $input->getOption('index-name');

        if (!\is_string($class)) {
            $io->error('The option --builder-class is required with --output-dir.');

            return false;
        }

        try {
            $builder = $this->renderer->renderBuilder(
                $palette,
                $class,
                \is_string($index) ? $index : 'default',
            );
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return false;
        }

        $css = $this->renderer->renderCss($palette);
        $legacyStyles = $this->renderer->renderLegacyStyles($palette);
        $files = [
            basename(str_replace('\\', '/', $class)) . '.php' => $builder,
            'palette.css'                                       => $css,
            'legacy-styles.json'                                => $legacyStyles,
        ];

        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            $io->error(\sprintf('Cannot create the directory "%s".', $directory));

            return false;
        }

        foreach ($files as $name => $contents) {
            $path = rtrim($directory, '/') . '/' . $name;

            if (false === @file_put_contents($path, $contents)) {
                $io->error(\sprintf('Cannot write "%s".', $path));

                return false;
            }

            $io->writeln(\sprintf(' <info>written</info> %s', $path));
        }

        return true;
    }

    /**
     * @return int<0, max>|null `null` when the value is not an integer between 0 and the maximum
     */
    private function integer(mixed $value, int $maximum): ?int
    {
        return \is_string($value) && 1 === preg_match('/^\d+$/', $value) && (int) $value <= $maximum
            ? max(0, (int) $value)
            : null;
    }

    /**
     * @return int<1, max>|null `null` when the value is not a strictly positive integer
     */
    private function positiveInt(mixed $value): ?int
    {
        return \is_string($value) && 1 === preg_match('/^[1-9]\d*$/', $value) ? max(1, (int) $value) : null;
    }
}
