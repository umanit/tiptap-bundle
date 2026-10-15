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
final class CollectionsTest extends TestCase
{
    public function testColorsSetIsCountableAndIterable(): void
    {
        $colors = [new Color('a', 'a'), new Color('b', 'b')];
        $set = new ColorsSet($colors);

        $this->assertCount(2, $set);
        $this->assertSame($colors, iterator_to_array($set));
    }

    public function testStylesSetsIsCountableAndIterable(): void
    {
        $sets = [new StyleSet('Banners', [new Style('Info', 'ut-banner-info')])];
        $stylesSets = new StylesSets($sets);

        $this->assertCount(1, $stylesSets);
        $this->assertSame($sets, iterator_to_array($stylesSets));
    }

    public function testTemplatesSetIsCountableAndIterable(): void
    {
        $templates = [new Template('Demo', '<p>Demo</p>'), new Template('Other', '<p>Other</p>', 'Description')];
        $set = new TemplatesSet($templates);

        $this->assertCount(2, $set);
        $this->assertSame($templates, iterator_to_array($set));
        $this->assertNull($templates[0]->description);
    }

    public function testEmptyCollections(): void
    {
        $this->assertCount(0, new ColorsSet([]));
        $this->assertCount(0, new StylesSets([]));
        $this->assertCount(0, new TemplatesSet([]));
    }
}
