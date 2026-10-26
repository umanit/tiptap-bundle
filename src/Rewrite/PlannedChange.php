<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Rewrite;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;

/**
 * A content to rewrite: what was read, its replacement and what the operation reports about it once it is written.
 */
final readonly class PlannedChange
{
    /**
     * @param string                     $reference Readable reference ({@see AuditedContent::describe()})
     * @param array<string, int<1, max>> $changes   What the operation did on the content, to report once written
     */
    public function __construct(
        public AuditedContent $content,
        public string $after,
        public string $reference,
        public array $changes = [],
    ) {
    }
}
