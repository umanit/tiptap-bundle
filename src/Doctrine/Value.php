<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine;

/**
 * Casts what the database returns (everything is `mixed`, whatever the driver gives: a string, an integer…).
 */
final class Value
{
    public static function toString(mixed $value): string
    {
        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    public static function toInt(mixed $value): int
    {
        return \is_numeric($value) ? (int) $value : 0;
    }
}
