<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

/**
 * What becomes of a content when the migration, or its rollback, handles it.
 */
enum MigrationOutcome: string
{
    /** Rewritten (or would be, in a simulation). */
    case Migrated = 'migrated';

    /** Already as the editor would write it: nothing to do. */
    case Unchanged = 'unchanged';

    /** Left as it is: the migration would lose some formatting which has not been accepted. */
    case Blocked = 'blocked';

    /** Left as it is: the text itself would no longer be the same. */
    case TextChanged = 'text_changed';

    /** Left as it is: the editor could not load it. */
    case Error = 'error';

    /** Left as it is: it was edited since it was read. */
    case Conflict = 'conflict';

    /** Original content put back by the rollback of a migration. */
    case Restored = 'restored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Migrated => 'migrated',
            self::Unchanged => 'already compliant',
            self::Blocked => 'blocked (formatting lost)',
            self::TextChanged => 'blocked (text changed)',
            self::Error => 'in error',
            self::Conflict => 'edited meanwhile',
            self::Restored => 'restored',
        };
    }
}
