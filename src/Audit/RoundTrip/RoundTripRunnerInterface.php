<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;

interface RoundTripRunnerInterface
{
    /**
     * Runs each content through the TipTap editor and reports what changed.
     *
     * The results are yielded as they come, in an order which is not the one of the contents.
     *
     * @param iterable<AuditedContent> $contents
     * @param positive-int             $workers  Number of processes which handle the contents in parallel
     * @param string|null              $legacyStylesFile JSON map of the legacy import mode: the inline colors and sizes
     *                                           it covers are converted to classes instead of being lost
     *
     * @return \Generator<int, RoundTripResult>
     *
     * @throws \RuntimeException If the editor cannot be started or stops before everything is handled
     */
    public function run(iterable $contents, int $workers = 4, ?string $legacyStylesFile = null): \Generator;
}
