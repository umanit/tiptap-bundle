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
    /** Number of rows fetched at once. */
    private const int PAGE_SIZE = 500;

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
     * The rows are read page by page, each one fetched before its contents are yielded: nothing stays open on the
     * connection while the caller writes on it.
     *
     * @throws \JsonException If a cell does not hold a valid value for its storage
     */
    public function read(): \Generator
    {
        $connection = $this->getConnection();
        $last = null;

        do {
            $rows = $this->fetchPage($connection, $last);

            foreach ($rows as [$id, $label, $raw]) {
                $last = $id;

                yield from $this->extractor->extract(
                    $this->getName(),
                    $this->getGroup(),
                    Value::toString($id),
                    null === $label ? null : Value::toString($label),
                    $this->storage->decode(Value::toString($raw)),
                );
            }
        } while (self::PAGE_SIZE === \count($rows));
    }

    public function replace(string $id, array $changes): array
    {
        return $this->storage->write($this->getConnection(), $this->column, $id, $changes);
    }

    /**
     * @param mixed $last Identifier of the last row of the previous page, as the database gave it
     *
     * @return list<list<mixed>> The identifier, the label and the value of the rows which follow it
     */
    private function fetchPage(Connection $connection, mixed $last): array
    {
        $platform = $connection->getDatabasePlatform();
        $column = $platform->quoteSingleIdentifier($this->column->column);
        $id = $platform->quoteSingleIdentifier($this->column->idColumn);

        $label = null === $this->column->labelColumn
            ? 'NULL'
            : $platform->quoteSingleIdentifier($this->column->labelColumn);

        $query = $connection
            ->createQueryBuilder()
            ->select($id, $label, $this->storage->getSelectExpression($connection, $column))
            ->from($platform->quoteSingleIdentifier($this->column->table))
            ->where($column . ' IS NOT NULL')
            ->orderBy($id)
            ->setMaxResults(self::PAGE_SIZE)
        ;

        if (null !== $last) {
            $query->andWhere($id . ' > :last')->setParameter('last', $last);
        }

        return $query->executeQuery()->fetchAllNumeric();
    }

    private function getConnection(): Connection
    {
        $connection = $this->registry->getConnection($this->column->connection);

        if (!$connection instanceof Connection) {
            $name = null === $this->column->connection
                ? 'default connection'
                : \sprintf('connection "%s"', $this->column->connection);

            throw new \LogicException(\sprintf('The %s is not a DBAL connection.', $name));
        }

        return $connection;
    }
}
