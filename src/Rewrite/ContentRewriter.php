<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Rewrite;

use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\WritableContentSourceInterface;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\MigrationPhase;

/**
 * What the operations which rewrite the contents of the sources (the cleaning, the migration) have in common: telling
 * the sources which can be written from the others, checking that the contents can be saved, and writing a plan.
 *
 * Each operation keeps what it decides: which content to plan, and what to report about it.
 *
 * @phpstan-import-type ProgressCallback from MigrationPhase
 */
final readonly class ContentRewriter
{
    public function __construct(private ?MigrationBackupInterface $backup = null)
    {
    }

    /**
     * @param list<ContentSourceInterface> $sources
     *
     * @return array{array<string, WritableContentSourceInterface>, list<string>} The sources which can be written by
     *                                                                            name, and the names of the others
     */
    public function partition(array $sources): array
    {
        $writable = [];
        $readOnly = [];

        foreach ($sources as $source) {
            if ($source instanceof WritableContentSourceInterface) {
                $writable[$source->getName()] = $source;
            } else {
                $readOnly[] = $source->getName();
            }
        }

        return [$writable, $readOnly];
    }

    /**
     * @throws \RuntimeException If the contents are to be rewritten and there is nowhere to save them
     */
    public function assertCanSave(bool $apply): void
    {
        if ($apply && !$this->backup?->isReady()) {
            throw new \RuntimeException('There is nowhere to save the contents before rewriting them.');
        }
    }

    /**
     * Writes the plan, or only reports it when it is not applied.
     *
     * @param array<string, WritableContentSourceInterface> $writable
     * @param \Closure(PlannedChange, bool): void           $onWritten  Called for each planned content, told whether
     *                                                                  it was replaced (always when not applied)
     * @param ProgressCallback|null                         $onProgress Called after each row
     */
    public function write(
        array $writable,
        RewritePlan $plan,
        string $rewriteId,
        bool $apply,
        \Closure $onWritten,
        ?\Closure $onProgress = null,
    ): void {
        $rows = $plan->getRows();
        $done = 0;

        foreach ($rows as $changes) {
            $written = $apply ? $this->applyRow($writable[$changes[0]->content->source], $rewriteId, $changes) : null;

            foreach ($changes as $change) {
                $onWritten($change, null === $written || \in_array($change->content->path, $written, true));
            }

            $onProgress?->__invoke(MigrationPhase::Writing, ++$done, \count($rows));
        }
    }

    /**
     * The contents are saved before they are rewritten: should the process stop in between, the backup of a content
     * which has not been rewritten is harmless, as a rollback only restores a content which is still the rewritten one.
     *
     * @param non-empty-list<PlannedChange> $changes
     *
     * @return list<string> The paths rewritten
     */
    private function applyRow(WritableContentSourceInterface $source, string $rewriteId, array $changes): array
    {
        $id = $changes[0]->content->id;
        $replacements = [];

        foreach ($changes as $change) {
            $path = $change->content->path;
            $before = $change->content->html;

            $this->backup?->record($rewriteId, $source->getName(), $id, $path, $before, $change->after);
            $replacements[$path] = [$before, $change->after];
        }

        // The keys of a PHP array which look like an integer are integers: the paths are made strings once, here
        $written = \array_map(\strval(...), $source->replace($id, $replacements));

        foreach ($changes as $change) {
            if (!\in_array($change->content->path, $written, true)) {
                $this->backup?->forget($rewriteId, $source->getName(), $id, $change->content->path);
            }
        }

        return $written;
    }
}
