<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Classification;

/**
 * What to do when a document loses its format.
 */
enum LossStatus: string
{
    /** A migration can convert it without loss (an inline style becomes a class of the editor). */
    case Convertible = 'convertible';

    /** No visible effect: it can be dropped without consequence. */
    case Acceptable = 'acceptable';

    /** Changes the appearance: to decide (extend the schema, convert differently, accept the loss). */
    case Decision = 'decision';

    /** No rule knows it yet. */
    case Unclassified = 'unclassified';

    public function getLabel(): string
    {
        return match ($this) {
            self::Convertible => 'convertible',
            self::Acceptable => 'no visible effect',
            self::Decision => 'to decide',
            self::Unclassified => 'unclassified',
        };
    }
}
