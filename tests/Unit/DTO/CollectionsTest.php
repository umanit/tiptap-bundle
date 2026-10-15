<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\DTO\Color;
use Umanit\TiptapBundle\DTO\ColorsSet;
use Umanit\TiptapBundle\DTO\Style;
use Umanit\TiptapBundle\DTO\StyleSet;
use Umanit\TiptapBundle\DTO\StylesSets;
use Umanit\TiptapBundle\DTO\Template;
use Umanit\TiptapBundle\DTO\TemplatesSet;

#[CoversClass(ColorsSet::class)]
#[CoversClass(StylesSets::class)]
#[CoversClass(TemplatesSet::class)]
#[CoversClass(StyleSet::class)]
#[CoversClass(Style::class)]
#[CoversClass(Template::class)]
class CollectionsTest extends TestCase
{
    public function testColorsSetIsCountableAndIterable(): void
    {
        $colors = [new Color('a', 'a'), new Color('b', 'b')];
        $set = new ColorsSet($colors);

        self::assertCount(2, $set);
        self::assertSame($colors, iterator_to_array($set));
    }

    public function testStylesSetsIsCountableAndIterable(): void
    {
        $sets = [new StyleSet('Banners', [new Style('Info', 'ut-banner-info')])];
        $stylesSets = new StylesSets($sets);

        self::assertCount(1, $stylesSets);
        self::assertSame($sets, iterator_to_array($stylesSets));
    }

    public function testTemplatesSetIsCountableAndIterable(): void
    {
        $templates = [new Template('Demo', '<p>Demo</p>'), new Template('Other', '<p>Other</p>', 'Description')];
        $set = new TemplatesSet($templates);

        self::assertCount(2, $set);
        self::assertSame($templates, iterator_to_array($set));
        self::assertNull($templates[0]->description);
    }

    public function testEmptyCollections(): void
    {
        self::assertCount(0, new ColorsSet([]));
        self::assertCount(0, new StylesSets([]));
        self::assertCount(0, new TemplatesSet([]));
    }
}
