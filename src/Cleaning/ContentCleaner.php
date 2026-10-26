<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\MigrationPhase;
use Umanit\TiptapBundle\Rewrite\ContentRewriter;
use Umanit\TiptapBundle\Rewrite\PlannedChange;
use Umanit\TiptapBundle\Rewrite\RewritePlan;

/**
 * Cleans the contents of the sources with {@see HtmlCleaner}, before they are migrated.
 *
 * It follows the same guarantees as the migration: each rewritten content is saved first, under an identifier that
 * `umanit:tiptap:migrate --rollback` puts back, and a content edited between its reading and its writing is left alone.
 *
 * @phpstan-import-type ProgressCallback from MigrationPhase
 */
final readonly class ContentCleaner
{
    private ContentRewriter $rewriter;

    public function __construct(
        private HtmlCleaner $cleaner,
        private ContentSourceRegistry $registry,
        ?MigrationBackupInterface $backup = null,
    ) {
        $this->rewriter = new ContentRewriter($backup);
    }

    /**
     * @param list<ContentSourceInterface> $sources    The sources which cannot be written are reported and left alone
     * @param ProgressCallback|null        $onProgress Called after each content read or row written
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
        [$writable, $readOnly] = $this->rewriter->partition($sources);

        foreach ($readOnly as $name) {
            $report->addReadOnlySource($name);
        }

        $this->rewriter->assertCanSave($options->apply);

        $plan = new RewritePlan();
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
                $plan->add(new PlannedChange($content, $result->html, $reference, $result->changes));

                if ($options->details) {
                    $report->addDetail(
                        $content->source,
                        new CleaningDetail($reference, $result->changes, $content->html, $result->html),
                    );
                }
            }
        }

        $this->rewriter->write(
            $writable,
            $plan,
            $cleaningId,
            $options->apply,
            static function (PlannedChange $change, bool $written) use ($report): void {
                $source = $change->content->source;

                $written
                    ? $report->add($source, CleaningOutcome::Cleaned, $change->reference, $change->changes)
                    : $report->add($source, CleaningOutcome::Conflict, $change->reference);
            },
            $onProgress,
        );

        if ($options->apply) {
            $report->setCleaningId($cleaningId);
        }

        return $report;
    }
}
