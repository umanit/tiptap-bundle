<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

/**
 * Outcome of a migration (or of its rollback), by source.
 */
class MigrationReport
{
    /** @var array<string, array<string, int>> Contents by source and by outcome */
    private array $counts = [];

    /** @var array<string, array<string, list<string>>> References of contents by source and by outcome */
    private array $examples = [];

    /** @var array<string, array<string, int>> Blocked contents by source and by category of loss */
    private array $blockedBy = [];

    /** @var array<string, array<string, list<MigrationDetail>>> Details by source and by outcome */
    private array $details = [];

    /** @var list<string> */
    private array $readOnlySources = [];

    private ?string $migrationId = null;

    public function __construct(
        private readonly int $maxExamples = 3,
    ) {
    }

    /**
     * @param list<string> $categories Categories of loss which block the content
     */
    public function add(string $source, MigrationOutcome $outcome, string $reference, array $categories = []): void
    {
        $key = $outcome->value;

        $this->counts[$source][$key] = ($this->counts[$source][$key] ?? 0) + 1;

        if (\count($this->examples[$source][$key] ?? []) < $this->maxExamples) {
            $this->examples[$source][$key][] = $reference;
        }

        foreach ($categories as $category) {
            $this->blockedBy[$source][$category] = ($this->blockedBy[$source][$category] ?? 0) + 1;
        }
    }

    /**
     * Only as many details as examples are kept, by source and by outcome.
     */
    public function addDetail(string $source, MigrationDetail $detail): void
    {
        $key = $detail->outcome->value;

        if (\count($this->details[$source][$key] ?? []) < $this->maxExamples) {
            $this->details[$source][$key][] = $detail;
        }
    }

    /**
     * @return list<MigrationDetail>
     */
    public function getDetails(string $source): array
    {
        return array_merge(...array_values($this->details[$source] ?? []));
    }

    /**
     * A source which cannot be written, and so is not migrated.
     */
    public function addReadOnlySource(string $source): void
    {
        $this->readOnlySources[] = $source;
    }

    /**
     * Identifier under which the rewritten contents have been saved.
     */
    public function setMigrationId(string $migrationId): void
    {
        $this->migrationId = $migrationId;
    }

    public function getMigrationId(): ?string
    {
        return $this->migrationId;
    }

    public function count(MigrationOutcome $outcome, ?string $source = null): int
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
        return array_keys($this->counts);
    }

    /**
     * @return list<string>
     */
    public function getReadOnlySources(): array
    {
        return $this->readOnlySources;
    }

    /**
     * @return list<string>
     */
    public function getExamples(string $source, MigrationOutcome $outcome): array
    {
        return $this->examples[$source][$outcome->value] ?? [];
    }

    /**
     * @return array<string, int> Categories of loss which block contents of the source, the most frequent first
     */
    public function getBlockedCategories(string $source): array
    {
        $categories = $this->blockedBy[$source] ?? [];

        arsort($categories);

        return $categories;
    }
}
