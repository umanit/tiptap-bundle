<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;

final readonly class CustomToolToolbarBuilder implements ToolbarBuilderInterface
{
    public static function getDefaultIndexName(): string
    {
        return 'custom';
    }

    public function build(): Toolbar
    {
        return new Toolbar([
            new ToolbarSection([
                new Tool('bold'),
                new Tool('highlight', command: 'toggleHighlight', title: 'Highlight', args: ['color' => 'yellow']),
                new Tool('callout', command: 'callout.open', title: 'Callout', label: 'Callout'),
            ]),
        ]);
    }
}
