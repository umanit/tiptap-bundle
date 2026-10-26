<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Umanit\TiptapBundle\Builder\ToolsBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;

final readonly class CustomToolsBuilder implements ToolsBuilderInterface
{
    public function build(): iterable
    {
        yield new Tool(
            'highlight',
            'Highlight',
            command: 'toggleHighlight',
            args: ['color' => 'yellow'],
            active: ['name' => 'highlight'],
        );
        yield new Tool('callout', 'Callout', command: 'callout.open', label: 'Callout');
    }
}
