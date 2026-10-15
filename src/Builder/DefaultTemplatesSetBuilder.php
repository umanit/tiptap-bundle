<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Umanit\TiptapBundle\DTO\Template;
use Umanit\TiptapBundle\DTO\TemplatesSet;

/**
 * Lowest priority: an application builder with the same name takes precedence over this one.
 */
#[AsTaggedItem(priority: -100)]
final readonly class DefaultTemplatesSetBuilder implements TemplatesSetBuilderInterface
{
    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): TemplatesSet
    {
        return new TemplatesSet([
            new Template(
                'wysiwyg.modal.template.items.demo.title',
                '<h1>Demo title</h1><p>This is an inserted template paragraph.</p>',
                'wysiwyg.modal.template.items.demo.desc',
            ),
            new Template(
                'wysiwyg.modal.template.items.table.title',
                '<table><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr></tbody></table>',
                'wysiwyg.modal.template.items.table.desc',
            ),
        ]);
    }
}
