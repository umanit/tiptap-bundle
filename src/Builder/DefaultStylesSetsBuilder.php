<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Umanit\TiptapBundle\DTO\Style;
use Umanit\TiptapBundle\DTO\StyleSet;
use Umanit\TiptapBundle\DTO\StylesSets;

/**
 * Lowest priority: an application builder with the same name takes precedence over this one.
 */
#[AsTaggedItem(priority: -100)]
final readonly class DefaultStylesSetsBuilder implements StylesSetsBuilderInterface
{
    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): StylesSets
    {
        return new StylesSets([
            new StyleSet('wysiwyg.style.section.banners', [
                new Style('wysiwyg.style.banner_info', 'ut-banner-info'),
                new Style('wysiwyg.style.banner_warning', 'ut-banner-warning'),
            ]),
            new StyleSet('wysiwyg.style.section.misc', [
                new Style('wysiwyg.style.button', 'ut-button'),
            ]),
            new StyleSet('wysiwyg.style.section.tables', [
                new Style('wysiwyg.style.table_striped', 'ut-table-striped'),
                new Style('wysiwyg.style.table_dotted', 'ut-table-dotted'),
            ]),
        ]);
    }
}
