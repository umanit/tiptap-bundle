<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Storage;

use Doctrine\DBAL\Connection;
use Umanit\TiptapBundle\Doctrine\ContentColumn;

/**
 * How a column stores its contents. The bundle knows the simple value and the JSON document; an application adds
 * what its database offers (the `hstore` of PostgreSQL…) by implementing this interface.
 */
interface ColumnStorageInterface
{
    /**
     * Name given to {@see ContentColumn::$storage}.
     */
    public function getName(): string;

    /**
     * What to select to read the column: the column itself, or an expression which gives it as text.
     *
     * @param string $quotedColumn The column, quoted
     */
    public function getSelectExpression(Connection $connection, string $quotedColumn): string;

    /**
     * @return mixed A string, or a structure (a locale => content map, a document…) walked at any depth
     *
     * @throws \JsonException If the value cannot be decoded
     */
    public function decode(string $raw): mixed;

    /**
     * Replaces contents of a row, only the ones which are still what was read.
     *
     * @param array<string, array{string, string}> $changes For each path: the content expected, its replacement
     *
     * @return list<string> The paths which were replaced
     */
    public function write(Connection $connection, ContentColumn $column, string $id, array $changes): array;
}
