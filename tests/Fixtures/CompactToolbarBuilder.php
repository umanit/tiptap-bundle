<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;
use Umanit\TiptapBundle\Provider\ToolProviderInterface;

final readonly class CompactToolbarBuilder implements ToolbarBuilderInterface
{
    public function __construct(
        private ToolProviderInterface $tools,
    ) {
    }

    public static function getDefaultIndexName(): string
    {
        return 'compact';
    }

    public function build(): Toolbar
    {
        return new Toolbar([new ToolbarSection([$this->tools->getTool('bold')])]);
    }
}
