<?php

declare(strict_types=1);

namespace Playground\Toolbar;

use Umanit\TiptapBundle\Builder\DefaultToolbarBuilder;
use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;

/**
 * The default toolbar of the bundle plus two tools of the application, whose JavaScript side is in
 * `assets/tiptap-custom.js`: a TipTap command (`toggleHighlight`) and a manager command (`callout.insert`).
 */
final readonly class PlaygroundToolbarBuilder implements ToolbarBuilderInterface
{
    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): Toolbar
    {
        $default = new DefaultToolbarBuilder()->build();

        return new Toolbar([
            ...$default->sections,
            new ToolbarSection([
                new Tool('highlight', command: 'toggleHighlight', title: 'Highlight', label: 'HL'),
                new Tool(
                    'callout',
                    command: 'callout.insert',
                    title: 'Callout',
                    icon: 'umanit-tiptap:square-pen',
                    args: ['text' => 'Callout'],
                ),
            ]),
        ]);
    }
}
