<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

/**
 * What becomes of a content when it is cleaned.
 */
enum CleaningOutcome: string
{
    /** Rewritten (or would be, in a simulation). */
    case Cleaned = 'cleaned';

    /** Nothing to remove. */
    case Unchanged = 'unchanged';

    /** Left as it is: cleaning it would change its text, or it is not a fragment the cleaner understands. */
    case Skipped = 'skipped';

    /** Left as it is: it was edited since it was read. */
    case Conflict = 'conflict';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cleaned => 'cleaned',
            self::Unchanged => 'nothing to clean',
            self::Skipped => 'skipped (text would change)',
            self::Conflict => 'edited meanwhile',
        };
    }
}
