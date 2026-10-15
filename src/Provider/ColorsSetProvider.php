<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\Builder\ColorsSetBuilderInterface;
use Umanit\TiptapBundle\DTO\ColorsSet;

final readonly class ColorsSetProvider implements ColorsSetProviderInterface
{
    /** @var array<string, ColorsSetBuilderInterface> */
    private array $colorsSet;

    /**
     * @param iterable<ColorsSetBuilderInterface> $colorsSet
     */
    public function __construct(
        iterable $colorsSet,
    ) {
        $indexed = [];

        foreach ($colorsSet as $builder) {
            $indexed[$builder::getDefaultIndexName()] = $builder;
        }

        $this->colorsSet = $indexed;
    }

    public function getColorsSet(string $name): ColorsSet
    {
        $colorsSet = $this->colorsSet[$name] ?? throw new \InvalidArgumentException(
            \sprintf('The colors set "%s" does not exist.', $name),
        );

        return $colorsSet->build();
    }
}
