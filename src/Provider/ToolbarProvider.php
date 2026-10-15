<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\DTO\Toolbar;

final readonly class ToolbarProvider implements ToolbarProviderInterface
{
    /** @var array<string, ToolbarBuilderInterface> */
    private array $toolbars;

    /**
     * @param iterable<ToolbarBuilderInterface> $toolbars
     */
    public function __construct(
        iterable $toolbars,
    ) {
        $indexed = [];

        foreach ($toolbars as $builder) {
            $indexed[$builder::getDefaultIndexName()] ??= $builder;
        }

        $this->toolbars = $indexed;
    }

    public function getToolbar(string $name): Toolbar
    {
        $toolbar = $this->toolbars[$name] ?? throw new \InvalidArgumentException(
            \sprintf('The toolbar "%s" does not exist.', $name),
        );

        return $toolbar->build();
    }
}
