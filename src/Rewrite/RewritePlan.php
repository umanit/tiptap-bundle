<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Rewrite;

/**
 * The contents to rewrite, grouped by row of their source so that a row is written once.
 */
final class RewritePlan
{
    /** @var array<string, array<string, list<PlannedChange>>> source => id => changes */
    private array $rows = [];

    public function add(PlannedChange $change): void
    {
        $this->rows[$change->content->source][$change->content->id][] = $change;
    }

    /**
     * @return list<non-empty-list<PlannedChange>> The changes of each row, which all come from the same source
     */
    public function getRows(): array
    {
        $rows = [];

        foreach ($this->rows as $sourceRows) {
            foreach ($sourceRows as $changes) {
                $rows[] = $changes;
            }
        }

        /** @var list<non-empty-list<PlannedChange>> $rows */
        return $rows;
    }
}
