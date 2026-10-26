<?php

declare(strict_types=1);

namespace Playground\Toolbar;

use Umanit\TiptapBundle\Builder\DefaultToolbarBuilder;
use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;
use Umanit\TiptapBundle\Provider\ToolProviderInterface;

/**
 * The default toolbar of the bundle plus the two tools of `PlaygroundToolsBuilder`.
 */
final readonly class PlaygroundToolbarBuilder implements ToolbarBuilderInterface
{
    public function __construct(
        private ToolProviderInterface $tools,
    ) {
    }

    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): Toolbar
    {
        $default = new DefaultToolbarBuilder($this->tools)->build();

        return new Toolbar([
            ...$default->sections,
            new ToolbarSection(array_map($this->tools->getTool(...), ['highlight', 'callout'])),
        ]);
    }
}
