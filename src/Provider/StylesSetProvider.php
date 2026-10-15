<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\Builder\StylesSetsBuilderInterface;
use Umanit\TiptapBundle\DTO\StylesSets;

final readonly class StylesSetProvider implements StylesSetProviderInterface
{
    /** @var array<string, StylesSetsBuilderInterface> */
    private array $stylesSets;

    /**
     * @param iterable<StylesSetsBuilderInterface> $stylesSets
     */
    public function __construct(
        iterable $stylesSets,
    ) {
        $indexed = [];

        foreach ($stylesSets as $builder) {
            $indexed[$builder::getDefaultIndexName()] ??= $builder;
        }

        $this->stylesSets = $indexed;
    }

    public function getStylesSet(string $name): StylesSets
    {
        $stylesSet = $this->stylesSets[$name] ?? throw new \InvalidArgumentException(
            \sprintf('The styles set "%s" does not exist.', $name),
        );

        return $stylesSet->build();
    }
}
