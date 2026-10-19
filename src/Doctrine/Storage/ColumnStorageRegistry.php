<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Storage;

final readonly class ColumnStorageRegistry
{
    /**
     * @param iterable<ColumnStorageInterface> $storages A storage declared again by a later one replaces the earlier
     */
    public function __construct(
        private iterable $storages,
    ) {
    }

    /**
     * @throws \InvalidArgumentException If no storage has this name
     */
    public function get(string $name): ColumnStorageInterface
    {
        $known = [];

        foreach ($this->storages as $storage) {
            $known[$storage->getName()] = $storage;
        }

        return $known[$name] ?? throw new \InvalidArgumentException(
            \sprintf(
                'Unknown column storage "%s". Known storages: %s.',
                $name,
                implode(', ', array_keys($known)),
            ),
        );
    }
}
