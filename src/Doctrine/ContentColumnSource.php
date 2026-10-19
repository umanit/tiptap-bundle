<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;
use Umanit\TiptapBundle\Audit\Source\WritableContentSourceInterface;
use Umanit\TiptapBundle\Doctrine\Storage\ColumnStorageInterface;

/**
 * Reads and rewrites, in plain SQL, the contents of a column: it depends neither on the entities of the application
 * nor on their hydration, and works on any database which holds the declared columns.
 */
final readonly class ContentColumnSource implements WritableContentSourceInterface
{
    public function __construct(
        private ContentColumn $column,
        private ColumnStorageInterface $storage,
        private ManagerRegistry $registry,
        private HtmlContentExtractor $extractor,
    ) {
    }

    public function getColumn(): ContentColumn
    {
        return $this->column;
    }

    public function getName(): string
    {
        return $this->column->getName();
    }

    public function getGroup(): string
    {
        return $this->column->group;
    }

    /**
     * @throws \JsonException If a cell does not hold a valid value for its storage
     */
    public function read(): \Generator
    {
        $connection = $this->getConnection();

        foreach ($connection->iterateNumeric($this->buildQuery($connection)) as [$id, $label, $raw]) {
            yield from $this->extractor->extract(
                $this->getName(),
                $this->getGroup(),
                Value::toString($id),
                null === $label ? null : Value::toString($label),
                $this->storage->decode(Value::toString($raw)),
            );
        }
    }

    public function replace(string $id, array $changes): array
    {
        return $this->storage->write($this->getConnection(), $this->column, $id, $changes);
    }

    private function buildQuery(Connection $connection): string
    {
        $column = $connection->getDatabasePlatform()->quoteSingleIdentifier($this->column->column);
        $id = $connection->getDatabasePlatform()->quoteSingleIdentifier($this->column->idColumn);

        $label = null === $this->column->labelColumn
            ? 'NULL'
            : $connection->getDatabasePlatform()->quoteSingleIdentifier($this->column->labelColumn);

        return \sprintf(
            'SELECT %s, %s, %s FROM %s WHERE %s IS NOT NULL ORDER BY %s',
            $id,
            $label,
            $this->storage->getSelectExpression($connection, $column),
            $connection->getDatabasePlatform()->quoteSingleIdentifier($this->column->table),
            $column,
            $id,
        );
    }

    private function getConnection(): Connection
    {
        $connection = $this->registry->getConnection();

        if (!$connection instanceof Connection) {
            throw new \LogicException('The default connection is not a DBAL connection.');
        }

        return $connection;
    }
}
