<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

/**
 * What {@see HtmlCleaner} made of a content.
 */
final readonly class CleaningResult
{
    /**
     * @param string                     $html    The cleaned content; the original one if nothing was cleaned or if
     *                                            cleaning it was not safe
     * @param array<string, int<1, max>> $changes Number of changes by rule (only the rules which changed something)
     * @param bool                       $skipped The content was left as it is, because cleaning it would have changed
     *                                            its text or lost something other than formatting
     */
    public function __construct(
        public string $html,
        public array $changes = [],
        public bool $skipped = false,
    ) {
    }

    public function isChanged(): bool
    {
        return [] !== $this->changes;
    }
}
