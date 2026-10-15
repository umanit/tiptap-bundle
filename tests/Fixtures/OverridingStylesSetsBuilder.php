<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Umanit\TiptapBundle\Builder\StylesSetsBuilderInterface;
use Umanit\TiptapBundle\DTO\Style;
use Umanit\TiptapBundle\DTO\StyleSet;
use Umanit\TiptapBundle\DTO\StylesSets;

/**
 * Takes the place of the bundle's `default` styles set.
 */
final readonly class OverridingStylesSetsBuilder implements StylesSetsBuilderInterface
{
    public const string CSS_CLASS = 'app-banner';

    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): StylesSets
    {
        return new StylesSets([new StyleSet('app.styles', [new Style('app.banner', self::CSS_CLASS)])]);
    }
}
