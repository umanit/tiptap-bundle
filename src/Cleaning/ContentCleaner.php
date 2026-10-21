<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Audit\Source\WritableContentSourceInterface;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\MigrationPhase;

/**
 * Cleans the contents of the sources with {@see HtmlCleaner}, before they are migrated.
 *
 * It follows the same guarantees as the migration: each rewritten content is saved first, under an identifier that
 * `umanit:tiptap:migrate --rollback` puts back, and a content edited between its reading and its writing is left alone.
 */
final readonly class ContentCleaner
{
    public function __construct(
        private HtmlCleaner $cleaner,
        private ContentSourceRegistry $registry,
        private ?MigrationBackupInterface $backup = null,
    ) {
    }

    /**
     * @param list<ContentSourceInterface>                                         $sources     The sources which
     *                                                                                          cannot
     *                                                                                          be written are reported
     *                                                                                          and left alone
     * @param (\Closure(MigrationPhase, int<0, max>, int<0, max>|null): void)|null $onProgress  Called after each
     *                                         content read or row written
     *
     * @throws \RuntimeException If there is nowhere to save the contents
     */
    public function clean(
        array $sources,
        string $cleaningId,
        CleaningOptions $options = new CleaningOptions(),
        ?\Closure $onProgress = null,
    ): CleaningReport {
        $report = new CleaningReport($options->maxExamples);
        /** @var array<string, WritableContentSourceInterface> $writable */
        $writable = [];

        foreach ($sources as $source) {
            if ($source instanceof WritableContentSourceInterface) {
                $writable[$source->getName()] = $source;
            } else {
                $report->addReadOnlySource($source->getName());
            }
        }

        if ($options->apply && !$this->backup?->isReady()) {
            throw new \RuntimeException('There is nowhere to save the contents before rewriting them.');
        }

        /** @var array<string, array<string, array<string, array{string, string}>>> $planned source => id => path => */
        $planned = [];
        /** @var array<string, array{string, array<string, int<1, max>>}> $references `source|id|path` => reference, changes */
        $references = [];
        $read = 0;

        foreach ($this->registry->read(\array_values($writable), $options->limit) as $content) {
            $onProgress?->__invoke(MigrationPhase::Analysis, ++$read, null);

            $reference = $content->describe();
            $result = $this->cleaner->clean($content->html, $options->only, $options->skip);

            if ($result->skipped) {
                $report->add($content->source, CleaningOutcome::Skipped, $reference);
            } elseif (!$result->isChanged()) {
                $report->add($content->source, CleaningOutcome::Unchanged, $reference);
            } else {
                $planned[$content->source][$content->id][$content->path] = [$content->html, $result->html];
                $key = $content->source . '|' . $content->id . '|' . $content->path;
                $references[$key] = [$reference, $result->changes];

                if ($options->details) {
                    $report->addDetail(
                        $content->source,
                        new CleaningDetail($reference, $result->changes, $content->html, $result->html),
                    );
                }
            }
        }

        $total = \array_sum(\array_map(\count(...), $planned));
        $done = 0;

        foreach ($planned as $sourceName => $rows) {
            foreach ($rows as $id => $changes) {
                $written = $options->apply
                    ? $this->applyRow($writable[$sourceName], $cleaningId, (string) $id, $changes)
                    : \array_map(\strval(...), \array_keys($changes));

                foreach (\array_keys($changes) as $path) {
                    [$reference, $ruleChanges] = $references[$sourceName . '|' . $id . '|' . $path];

                    \in_array((string) $path, $written, true)
                        ? $report->add($sourceName, CleaningOutcome::Cleaned, $reference, $ruleChanges)
                        : $report->add($sourceName, CleaningOutcome::Conflict, $reference);
                }

                $onProgress?->__invoke(MigrationPhase::Writing, ++$done, $total);
            }
        }

        if ($options->apply) {
            $report->setCleaningId($cleaningId);
        }

        return $report;
    }

    /**
     * @param array<string, array{string, string}> $changes
     *
     * @return list<string> The paths rewritten
     */
    private function applyRow(
        WritableContentSourceInterface $source,
        string $cleaningId,
        string $id,
        array $changes,
    ): array {
        foreach ($changes as $path => [$before, $after]) {
            $this->backup?->record($cleaningId, $source->getName(), $id, (string) $path, $before, $after);
        }

        $written = \array_map(\strval(...), $source->replace($id, $changes));

        foreach (\array_keys($changes) as $path) {
            if (!\in_array((string) $path, $written, true)) {
                $this->backup?->forget($cleaningId, $source->getName(), $id, (string) $path);
            }
        }

        return $written;
    }
}
