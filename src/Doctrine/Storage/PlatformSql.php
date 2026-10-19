<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Storage;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * The few SQL fragments which differ between the platforms (PostgreSQL, SQLite, MySQL and MariaDB).
 */
final class PlatformSql
{
    /**
     * A column, or a JSON document, as text.
     */
    public static function castToText(AbstractPlatform $platform, string $expression): string
    {
        return \sprintf('CAST(%s AS %s)', $expression, $platform instanceof AbstractMySQLPlatform ? 'CHAR' : 'TEXT');
    }

    /**
     * Compares an expression to a parameter byte for byte: MySQL and MariaDB compare texts with a collation which
     * ignores the case, and the trailing spaces, so that a content edited in the meantime could go unnoticed.
     */
    public static function equalsExactly(AbstractPlatform $platform, string $expression, string $parameter): string
    {
        return $platform instanceof AbstractMySQLPlatform
            ? \sprintf('BINARY %s = %s', $expression, $parameter)
            : \sprintf('%s = %s', $expression, $parameter);
    }
}
