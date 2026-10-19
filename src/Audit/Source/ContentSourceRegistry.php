<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Source;

final readonly class ContentSourceRegistry
{
    public const string DEFAULT_GROUP = 'content';

    /**
     * @param iterable<ContentSourceProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
    ) {
    }

    /**
     * @param string|null  $group Only keeps the sources of this group
     * @param list<string> $names Only keeps these sources
     *
     * @return list<ContentSourceInterface>
     *
     * @throws \InvalidArgumentException If a name does not match any source
     */
    public function getSources(?string $group = null, array $names = []): array
    {
        $all = [];

        // A source declared again by a later provider replaces the earlier one
        foreach ($this->providers as $provider) {
            foreach ($provider->getSources() as $source) {
                $all[$source->getName()] = $source;
            }
        }

        foreach ($names as $name) {
            if (!isset($all[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Unknown content source "%s". Known sources: %s.',
                    $name,
                    implode(', ', array_keys($all)),
                ));
            }
        }

        return array_values(array_filter(
            $all,
            static function (ContentSourceInterface $source) use ($group, $names): bool {
                if (null !== $group && $group !== $source->getGroup()) {
                    return false;
                }

                return [] === $names || \in_array($source->getName(), $names, true);
            },
        ));
    }
}
