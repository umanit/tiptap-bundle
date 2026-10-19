<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Storage;

use Doctrine\DBAL\Connection;
use Umanit\TiptapBundle\Doctrine\ContentColumn;

/**
 * HTML contents scattered in a JSON document, at any depth. The path of a content is the keys which lead to it,
 * joined by dots (`items.0.body`).
 */
final class JsonColumnStorage implements ColumnStorageInterface
{
    private const int JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    public function getName(): string
    {
        return ContentColumn::STORAGE_JSON;
    }

    public function getSelectExpression(Connection $connection, string $quotedColumn): string
    {
        return PlatformSql::castToText($connection->getDatabasePlatform(), $quotedColumn);
    }

    public function decode(string $raw): mixed
    {
        return json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * The document is read again, changed at the given paths and written back as a whole: the write only happens
     * if the document is still the one which was read.
     */
    public function write(Connection $connection, ContentColumn $column, string $id, array $changes): array
    {
        $platform = $connection->getDatabasePlatform();
        $table = $connection->getDatabasePlatform()->quoteSingleIdentifier($column->table);
        $quoted = $connection->getDatabasePlatform()->quoteSingleIdentifier($column->column);
        $idColumn = $connection->getDatabasePlatform()->quoteSingleIdentifier($column->idColumn);
        $asText = PlatformSql::castToText($platform, $quoted);

        $original = $connection->fetchOne(
            \sprintf('SELECT %s FROM %s WHERE %s = :id', $asText, $table, $idColumn),
            ['id' => $id],
        );

        if (!\is_string($original)) {
            return [];
        }

        $written = [];
        $document = $this->replaceIn($this->decode($original), '', $changes, $written);

        if ([] === $written) {
            return [];
        }

        $count = $connection->executeStatement(
            \sprintf(
                'UPDATE %s SET %s = :document WHERE %s = :id AND %s',
                $table,
                $quoted,
                $idColumn,
                PlatformSql::equalsExactly($platform, $asText, ':original'),
            ),
            ['document' => json_encode($document, self::JSON_FLAGS), 'id' => $id, 'original' => $original],
        );

        return 0 < (int) $count ? $written : [];
    }

    /**
     * @param array<string, array{string, string}> $changes
     * @param list<string>                         $written
     */
    private function replaceIn(mixed $node, string $path, array $changes, array &$written): mixed
    {
        if (\is_string($node)) {
            if (isset($changes[$path]) && $changes[$path][0] === $node) {
                $written[] = $path;

                return $changes[$path][1];
            }

            return $node;
        }

        if (\is_array($node)) {
            foreach ($node as $key => $child) {
                // Same paths as the ones the contents to audit are given
                $childPath = '' === $path ? (string) $key : $path . '.' . $key;
                $node[$key] = $this->replaceIn($child, $childPath, $changes, $written);
            }
        }

        return $node;
    }
}
