<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Migration;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Audit\Source\WritableContentSourceInterface;

/**
 * A source which holds its contents in memory, and replaces them only if they are the ones expected.
 */
final class InMemoryWritableSource implements WritableContentSourceInterface
{
    /**
     * @param array<array-key, array<array-key, string>> $rows Content by path, by row identifier
     */
    public function __construct(
        private readonly string $name,
        public array $rows,
        private readonly string $group = ContentSourceRegistry::DEFAULT_GROUP,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function read(): \Generator
    {
        // Contents are read at once, as a driver which fetches a page does: edits made afterwards are not seen
        $snapshot = $this->rows;

        foreach ($snapshot as $id => $paths) {
            foreach ($paths as $path => $html) {
                yield new AuditedContent($this->name, $this->group, (string) $id, null, (string) $path, $html);
            }
        }
    }

    public function replace(string $id, array $changes): array
    {
        $written = [];

        foreach ($changes as $path => [$expected, $replacement]) {
            if (($this->rows[$id][$path] ?? null) === $expected) {
                $this->rows[$id][$path] = $replacement;
                $written[] = (string) $path;
            }
        }

        return $written;
    }
}
