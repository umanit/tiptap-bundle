<?php

declare(strict_types=1);

namespace Playground\Toolbar;

use Umanit\TiptapBundle\Builder\ToolsBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;

/**
 * Two tools of the application, whose JavaScript side is in `assets/tiptap-custom.js`: a TipTap command
 * (`toggleHighlight`) and a manager command (`callout.insert`).
 */
final readonly class PlaygroundToolsBuilder implements ToolsBuilderInterface
{
    public function build(): iterable
    {
        yield new Tool(
            'highlight',
            'Highlight',
            command: 'toggleHighlight',
            label: 'HL',
            active: ['name' => 'highlight'],
        );
        yield new Tool(
            'callout',
            'Callout',
            command: 'callout.insert',
            icon: 'umanit-tiptap:square-pen',
            args: ['text' => 'Callout'],
        );
    }
}
