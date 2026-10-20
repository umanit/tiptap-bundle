<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Umanit\TiptapBundle\Migration\MigrationPhase;

/**
 * Shows a progress bar for each phase of a migration, so that a long run does not look frozen.
 */
final class ProgressTracker
{
    private ?ProgressBar $bar = null;
    private ?MigrationPhase $phase = null;

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly bool $enabled,
    ) {
    }

    /**
     * @param int<0, max>      $done
     * @param int<0, max>|null $total `null` when it is not known yet
     */
    public function update(MigrationPhase $phase, int $done, ?int $total): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($phase !== $this->phase) {
            $this->finish();

            $this->phase = $phase;
            $this->bar = $this->io->createProgressBar($total ?? 0);
            $this->bar->setFormat(
                match ($phase) {
                    MigrationPhase::Analysis => ' %current% contents analyzed [%elapsed:6s%]',
                    MigrationPhase::Writing => ' Writing %current%/%max% [%bar%] %percent:3s%% [%elapsed:6s%]',
                    MigrationPhase::Rollback => ' Restoring %current%/%max% [%bar%] %percent:3s%% [%elapsed:6s%]',
                },
            );
            $this->bar->setRedrawFrequency(50);
        }

        $this->bar?->setProgress($done);
    }

    public function finish(): void
    {
        if (null === $this->bar) {
            return;
        }

        $this->bar->finish();
        $this->io->newLine(2);
        $this->bar = null;
        $this->phase = null;
    }

    public function clear(): void
    {
        $this->bar?->clear();
        $this->bar = null;
        $this->phase = null;
    }
}
