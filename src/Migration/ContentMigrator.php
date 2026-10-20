<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\Classification\LossStatus;
use Umanit\TiptapBundle\Audit\RoundTrip\LostSignal;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripRunnerInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Audit\Source\WritableContentSourceInterface;
use Umanit\TiptapBundle\Migration\Backup\BackupEntry;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;

/**
 * Rewrites the contents written with another editor as the TipTap editor writes them.
 *
 * The converted content is what the schema of the editor produces, that is what the editor would do when the content
 * is opened and saved. A content is rewritten only if that loses neither text nor formatting which has not been
 * accepted; each rewritten content is saved first, so that the migration can be undone.
 */
final readonly class ContentMigrator
{
    public function __construct(
        private RoundTripRunnerInterface $runner,
        private LossClassifier $classifier,
        private ContentSourceRegistry $registry,
        private ?MigrationBackupInterface $backup = null,
    ) {
    }

    /**
     * @param list<ContentSourceInterface>                                          $sources    The sources which
     *                                                                                          cannot be written are
     *                                                                                          reported and left alone
     * @param (\Closure(MigrationPhase, int<0, max>, int<0, max>|null): void)|null $onProgress Called after each
     *                                                                                          content analyzed or row
     *                                                                                          written, with the total
     *                                                                                          when it is known
     *
     * @throws \RuntimeException If the editor cannot start, or if there is nowhere to save the contents
     */
    public function migrate(
        array $sources,
        string $migrationId,
        MigrationOptions $options = new MigrationOptions(),
        ?\Closure $onProgress = null,
    ): MigrationReport {
        $report = new MigrationReport($options->maxExamples);
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
        /** @var array<string, string> $references Readable reference, by `source|id|path` */
        $references = [];

        $results = $this->runner->run(
            $this->registry->read(array_values($writable), $options->limit),
            $options->workers,
            $options->legacyStylesFile,
        );

        $analyzed = 0;

        foreach ($results as $result) {
            $onProgress?->__invoke(MigrationPhase::Analysis, ++$analyzed, null);

            $content = $result->content;
            $reference = $content->describe();
            $blocking = $this->getBlockingCategories($result, $options->accepted);

            if (null !== $result->error || null === $result->after) {
                $report->add($content->source, MigrationOutcome::Error, $reference);
                $this->addDetail($report, $options, $result, MigrationOutcome::Error, $reference);
            } elseif ($result->textChanged) {
                $report->add($content->source, MigrationOutcome::TextChanged, $reference);
                $this->addDetail($report, $options, $result, MigrationOutcome::TextChanged, $reference);
            } elseif ($result->after === $content->html) {
                $report->add($content->source, MigrationOutcome::Unchanged, $reference);
            } elseif ([] !== $blocking) {
                $report->add($content->source, MigrationOutcome::Blocked, $reference, $blocking);
                $this->addDetail($report, $options, $result, MigrationOutcome::Blocked, $reference);
            } else {
                $planned[$content->source][$content->id][$content->path] = [$content->html, $result->after];
                $references[$content->source . '|' . $content->id . '|' . $content->path] = $reference;
            }
        }

        $total = array_sum(array_map(count(...), $planned));
        $done = 0;

        foreach ($planned as $sourceName => $rows) {
            foreach ($rows as $id => $changes) {
                $written = $options->apply
                    ? $this->applyRow($writable[$sourceName], $migrationId, (string) $id, $changes)
                    : array_keys($changes);

                foreach (array_keys($changes) as $path) {
                    $report->add(
                        $sourceName,
                        \in_array((string) $path, array_map(strval(...), $written), true)
                            ? MigrationOutcome::Migrated
                            : MigrationOutcome::Conflict,
                        $references[$sourceName . '|' . $id . '|' . $path],
                    );
                }

                $onProgress?->__invoke(MigrationPhase::Writing, ++$done, $total);
            }
        }

        if ($options->apply) {
            $report->setMigrationId($migrationId);
        }

        return $report;
    }

    /**
     * Puts back the original contents of a migration, except the ones edited since.
     *
     * @param (\Closure(MigrationPhase, int<0, max>, int<0, max>|null): void)|null $onProgress Called after each
     *                                                                                          content restored
     *
     * @throws \RuntimeException If there is no backup storage
     */
    public function rollback(
        string $migrationId,
        bool $apply = false,
        int $maxExamples = 3,
        ?\Closure $onProgress = null,
    ): MigrationReport {
        $backup = $this->backup;

        if (null === $backup || !$backup->isReady()) {
            throw new \RuntimeException('There is no backup to restore the contents from.');
        }

        $report = new MigrationReport($maxExamples);
        /** @var array<string, ContentSourceInterface> $sources */
        $sources = [];

        foreach ($this->registry->getSources() as $source) {
            $sources[$source->getName()] = $source;
        }

        $entries = $backup->findToRestore($migrationId);
        $done = 0;

        foreach ($entries as $entry) {
            $source = $sources[$entry->source] ?? null;
            $reference = $entry->id . ('' !== $entry->path ? '/' . $entry->path : '');

            if (!$source instanceof WritableContentSourceInterface) {
                $report->add($entry->source, MigrationOutcome::Error, $reference);
            } elseif (!$apply) {
                $report->add($entry->source, MigrationOutcome::Restored, $reference);
            } else {
                $report->add($entry->source, $this->restore($source, $backup, $entry), $reference);
            }

            $onProgress?->__invoke(MigrationPhase::Rollback, ++$done, \count($entries));
        }

        return $report;
    }

    private function restore(
        WritableContentSourceInterface $source,
        MigrationBackupInterface $backup,
        BackupEntry $entry,
    ): MigrationOutcome {
        $written = $source->replace($entry->id, [$entry->path => [$entry->after, $entry->before]]);

        if ([] === $written) {
            return MigrationOutcome::Conflict;
        }

        $backup->markRestored($entry);

        return MigrationOutcome::Restored;
    }

    /**
     * The contents are saved before they are rewritten: should the process stop in between, the backup of a content
     * which has not been rewritten is harmless, as a rollback only restores a content which is still the migrated one.
     *
     * @param array<string, array{string, string}> $changes
     *
     * @return list<string> The paths rewritten
     */
    private function applyRow(
        WritableContentSourceInterface $source,
        string $migrationId,
        string $id,
        array $changes,
    ): array {
        foreach ($changes as $path => [$before, $after]) {
            $this->backup?->record($migrationId, $source->getName(), $id, (string) $path, $before, $after);
        }

        $written = $source->replace($id, $changes);

        foreach (array_keys($changes) as $path) {
            if (!\in_array((string) $path, array_map(strval(...), $written), true)) {
                $this->backup?->forget($migrationId, $source->getName(), $id, (string) $path);
            }
        }

        return $written;
    }

    private function addDetail(
        MigrationReport $report,
        MigrationOptions $options,
        RoundTripResult $result,
        MigrationOutcome $outcome,
        string $reference,
    ): void {
        if (!$options->details) {
            return;
        }

        $reasons = array_map(
            function (LostSignal $lost): string {
                $category = $this->classifier->classify($lost->signal);

                return \sprintf(
                    '%s (%s, %s): %d → %d',
                    $lost->signal,
                    $category->key,
                    $category->status->value,
                    $lost->before,
                    $lost->after,
                );
            },
            $result->lost,
        );

        if (null !== $result->error) {
            array_unshift($reasons, $result->error);
        }

        if ($result->textChanged) {
            array_unshift($reasons, 'the text itself changes');
        }

        $report->addDetail(
            $result->content->source,
            new MigrationDetail($reference, $outcome, $reasons, $result->content->html, $result->after),
        );
    }

    /**
     * @param list<string> $accepted
     *
     * @return list<string> Categories of loss of the content which have not been accepted
     */
    private function getBlockingCategories(RoundTripResult $result, array $accepted): array
    {
        $blocking = [];

        foreach ($result->lost as $lost) {
            $category = $this->classifier->classify($lost->signal);

            if (
                LossStatus::Acceptable !== $category->status
                && !\in_array($category->key, $accepted, true)
                && !\in_array($category->status->value, $accepted, true)
            ) {
                $blocking[$category->key] = $category->key;
            }
        }

        return array_values($blocking);
    }
}
