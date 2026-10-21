<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

/**
 * Outcome of a cleaning, by source and by rule.
 */
class CleaningReport
{
    /** @var array<string, array<string, int>> Contents by source and by outcome */
    private array $counts = [];

    /** @var array<string, array<string, list<string>>> References by source and by outcome */
    private array $examples = [];

    /** @var array<string, array<string, array{contents: int, changes: int}>> By source and by rule */
    private array $rules = [];

    /** @var array<string, list<CleaningDetail>> */
    private array $details = [];

    /** @var list<string> */
    private array $readOnlySources = [];

    private ?string $cleaningId = null;

    public function __construct(
        private readonly int $maxExamples = 3,
    ) {
    }

    /**
     * @param array<string, int<1, max>> $changes Number of changes by rule, for a content which has been cleaned
     */
    public function add(string $source, CleaningOutcome $outcome, string $reference, array $changes = []): void
    {
        $key = $outcome->value;

        $this->counts[$source][$key] = ($this->counts[$source][$key] ?? 0) + 1;

        if (\count($this->examples[$source][$key] ?? []) < $this->maxExamples) {
            $this->examples[$source][$key][] = $reference;
        }

        foreach ($changes as $rule => $count) {
            $stats = $this->rules[$source][$rule] ?? ['contents' => 0, 'changes' => 0];

            $this->rules[$source][$rule] = [
                'contents' => $stats['contents'] + 1,
                'changes'  => $stats['changes'] + $count,
            ];
        }
    }

    public function addDetail(string $source, CleaningDetail $detail): void
    {
        if (\count($this->details[$source] ?? []) < $this->maxExamples) {
            $this->details[$source][] = $detail;
        }
    }

    /**
     * @return list<CleaningDetail>
     */
    public function getDetails(string $source): array
    {
        return $this->details[$source] ?? [];
    }

    public function addReadOnlySource(string $source): void
    {
        $this->readOnlySources[] = $source;
    }

    /**
     * @return list<string>
     */
    public function getReadOnlySources(): array
    {
        return $this->readOnlySources;
    }

    /**
     * Identifier under which the rewritten contents have been saved (to give to `umanit:tiptap:migrate --rollback`).
     */
    public function setCleaningId(string $cleaningId): void
    {
        $this->cleaningId = $cleaningId;
    }

    public function getCleaningId(): ?string
    {
        return $this->cleaningId;
    }

    public function count(CleaningOutcome $outcome, ?string $source = null): int
    {
        $total = 0;

        foreach ($this->counts as $name => $counts) {
            if (null === $source || $source === $name) {
                $total += $counts[$outcome->value] ?? 0;
            }
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    public function getSources(): array
    {
        return \array_keys($this->counts);
    }

    /**
     * @return list<string>
     */
    public function getExamples(string $source, CleaningOutcome $outcome): array
    {
        return $this->examples[$source][$outcome->value] ?? [];
    }

    /**
     * @return array<string, array{contents: int, changes: int}> The rules which changed contents of the source, the
     *                                                          most frequent first
     */
    public function getRules(string $source): array
    {
        $rules = $this->rules[$source] ?? [];

        \uasort($rules, static fn(array $a, array $b): int => $b['contents'] <=> $a['contents']);

        return $rules;
    }
}
