<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Storage;

use Doctrine\DBAL\Connection;
use Umanit\TiptapBundle\Doctrine\ContentColumn;

/**
 * One HTML content per row.
 */
final class ScalarColumnStorage implements ColumnStorageInterface
{
    public function getName(): string
    {
        return ContentColumn::STORAGE_SCALAR;
    }

    public function getSelectExpression(Connection $connection, string $quotedColumn): string
    {
        return $quotedColumn;
    }

    public function decode(string $raw): string
    {
        return $raw;
    }

    public function write(Connection $connection, ContentColumn $column, string $id, array $changes): array
    {
        $quoted = $connection->getDatabasePlatform()->quoteSingleIdentifier($column->column);
        $sql = \sprintf(
            'UPDATE %s SET %s = :after WHERE %s = :id AND %s',
            $connection->getDatabasePlatform()->quoteSingleIdentifier($column->table),
            $quoted,
            $connection->getDatabasePlatform()->quoteSingleIdentifier($column->idColumn),
            PlatformSql::equalsExactly($connection->getDatabasePlatform(), $quoted, ':before'),
        );
        $written = [];

        foreach ($changes as $path => [$before, $after]) {
            if (0 < (int) $connection->executeStatement($sql, ['after' => $after, 'id' => $id, 'before' => $before])) {
                $written[] = (string) $path;
            }
        }

        return $written;
    }
}
