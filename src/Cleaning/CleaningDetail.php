<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

/**
 * A cleaned content given as an example in the report.
 */
final readonly class CleaningDetail
{
    /**
     * @param array<string, int<1, max>> $changes Number of changes by rule
     */
    public function __construct(
        public string $reference,
        public array $changes,
        public string $before,
        public string $after,
    ) {
    }
}
