<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;
use Umanit\TiptapBundle\Provider\ToolProviderInterface;

/**
 * Lowest priority: an application builder with the same name takes precedence over this one.
 */
#[AsTaggedItem(priority: -100)]
final readonly class DefaultToolbarBuilder implements ToolbarBuilderInterface
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
        $sections = [
            ['undo', 'redo'],
            ['cut', 'copy', 'paste'],
            ['paragraph', 'heading1', 'heading2', 'heading3'],
            ['color', 'bgColor', 'fontSize'],
            ['styles'],
            ['bold', 'italic', 'underline', 'strike'],
            ['subscript', 'superscript'],
            ['alignLeft', 'alignCenter', 'alignRight', 'alignJustify'],
            ['bulletList', 'orderedList', 'indent', 'outdent', 'listTight'],
            ['anchor', 'link', 'unlink'],
            ['blockquote', 'image', 'table'],
            ['template', 'source'],
        ];

        return new Toolbar(array_map(
            fn(array $names): ToolbarSection => new ToolbarSection(array_map($this->tools->getTool(...), $names)),
            $sections,
        ));
    }
}
