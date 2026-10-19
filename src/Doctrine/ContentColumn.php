<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine;

use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;

/**
 * A database column which holds HTML contents written in another editor.
 */
final readonly class ContentColumn
{
    /** One HTML content per row. */
    public const string STORAGE_SCALAR = 'scalar';

    /** HTML contents scattered in a `json`/`jsonb` document, at any depth. */
    public const string STORAGE_JSON = 'json';

    /**
     * @param string      $table       Name of the table
     * @param string      $column      Name of the column
     * @param string      $storage     How the column stores the contents: the name of a {@see ColumnStorageInterface}
     *                                 ({@see self::STORAGE_SCALAR}, {@see self::STORAGE_JSON}, or the application's)
     * @param string      $idColumn    Column which identifies the row (a single column)
     * @param string|null $labelColumn Readable column which completes the identifier in the examples of a report
     * @param string      $group       Lets the columns be audited and migrated by batches (a field handled apart)
     */
    public function __construct(
        public string $table,
        public string $column,
        public string $storage = self::STORAGE_SCALAR,
        public string $idColumn = 'id',
        public ?string $labelColumn = null,
        public string $group = ContentSourceRegistry::DEFAULT_GROUP,
    ) {
    }

    public function getName(): string
    {
        return $this->table . '.' . $this->column;
    }
}
