<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;
use Umanit\TiptapBundle\Doctrine\Storage\ColumnStorageRegistry;

/**
 * Turns the columns declared by the {@see ContentColumnProviderInterface} (of the bundle, the application, the
 * libraries it relies on) into content sources.
 */
final readonly class ContentColumnSourceProvider implements ContentSourceProviderInterface
{
    /**
     * @param iterable<ContentColumnProviderInterface> $columnProviders
     */
    public function __construct(
        private iterable $columnProviders,
        private ColumnStorageRegistry $storages,
        private ManagerRegistry $registry,
        private HtmlContentExtractor $extractor,
    ) {
    }

    public function getSources(): iterable
    {
        $columns = [];

        // A column declared again by a later provider replaces the earlier one
        foreach ($this->columnProviders as $provider) {
            foreach ($provider->getColumns() as $column) {
                $columns[$column->getName()] = $column;
            }
        }

        foreach ($columns as $column) {
            yield new ContentColumnSource(
                $column,
                $this->storages->get($column->storage),
                $this->registry,
                $this->extractor,
            );
        }
    }
}
