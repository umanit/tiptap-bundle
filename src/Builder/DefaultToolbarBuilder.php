<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;

/**
 * Lowest priority: an application builder with the same name takes precedence over this one.
 */
#[AsTaggedItem(priority: -100)]
final readonly class DefaultToolbarBuilder implements ToolbarBuilderInterface
{
    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): Toolbar
    {
        return new Toolbar([
            new ToolbarSection([
                new Tool('undo'),
                new Tool('redo'),
            ]),
            new ToolbarSection([
                new Tool('cut'),
                new Tool('copy'),
                new Tool('paste'),
            ]),
            new ToolbarSection([
                new Tool('paragraph'),
                new Tool('heading1'),
                new Tool('heading2'),
                new Tool('heading3'),
            ]),
            new ToolbarSection([
                new Tool('color'),
                new Tool('bgColor'),
                new Tool('fontSize'),
            ]),
            new ToolbarSection([
                new Tool('styles'),
            ]),
            new ToolbarSection([
                new Tool('bold'),
                new Tool('italic'),
                new Tool('underline'),
                new Tool('strike'),
            ]),
            new ToolbarSection([
                new Tool('subscript'),
                new Tool('superscript'),
            ]),
            new ToolbarSection([
                new Tool('alignLeft'),
                new Tool('alignCenter'),
                new Tool('alignRight'),
                new Tool('alignJustify'),
            ]),
            new ToolbarSection([
                new Tool('bulletList'),
                new Tool('orderedList'),
                new Tool('indent'),
                new Tool('outdent'),
                new Tool('listTight'),
            ]),
            new ToolbarSection([
                new Tool('anchor'),
                new Tool('link'),
                new Tool('unlink'),
            ]),
            new ToolbarSection([
                new Tool('blockquote'),
                new Tool('image'),
                new Tool('table'),
            ]),
            new ToolbarSection([
                new Tool('template'),
                new Tool('source'),
            ]),
        ]);
    }
}
