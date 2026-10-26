<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Palette;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Palette\Palette;
use Umanit\TiptapBundle\Palette\PaletteBuilder;
use Umanit\TiptapBundle\Palette\PaletteColor;
use Umanit\TiptapBundle\Palette\PaletteSize;
use Umanit\TiptapBundle\Palette\StyleInventory;

#[CoversClass(PaletteBuilder::class)]
#[CoversClass(Palette::class)]
#[CoversClass(PaletteColor::class)]
#[CoversClass(PaletteSize::class)]
final class PaletteBuilderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function families(): iterable
    {
        yield 'white' => ['#ffffff', 'white'];
        yield 'black' => ['#000000', 'black'];
        yield 'grey' => ['#808080', 'grey'];
        yield 'red' => ['#dc3545', 'red'];
        yield 'orange' => ['#fd7e14', 'orange'];
        yield 'amber' => ['#ff9900', 'orange'];
        yield 'yellow' => ['#ffc107', 'yellow'];
        yield 'pure yellow' => ['#ffff00', 'yellow'];
        yield 'green' => ['#198754', 'green'];
        yield 'cyan' => ['#00ffff', 'cyan'];
        yield 'blue' => ['#0d6efd', 'blue'];
        yield 'purple' => ['#8000ff', 'purple'];
        yield 'pink' => ['#ff00aa', 'pink'];
    }

    public function testGroupsCloseColorsAroundTheMostUsedOne(): void
    {
        $palette = new PaletteBuilder()->build($this->inventory([
            'color'           => [['#ff0000', 5], ['#fa0a0a', 2], ['#0000ff', 3]],
            'backgroundColor' => [['#f00505', 1], ['#00ff00', 1]],
        ]), 16);

        $this->assertCount(3, $palette->colors);
        $this->assertSame(['red-1', '#ff0000'], [$palette->colors[0]->name, $palette->colors[0]->hex]);
        $this->assertSame(['#ff0000' => 5, '#fa0a0a' => 2, '#f00505' => 1], $palette->colors[0]->members);
        $this->assertSame(8, $palette->colors[0]->getUsage());
        $this->assertSame('blue-1', $palette->colors[1]->name);
        $this->assertSame('green-1', $palette->colors[2]->name);
    }

    public function testKeepsDistantColorsApartAndNumbersThePerFamily(): void
    {
        $palette = new PaletteBuilder()->build($this->inventory([
            'color' => [['#ff0000', 3], ['#cc0000', 2]],
        ]), 16);

        $names = array_map(static fn(PaletteColor $color): string => $color->name, $palette->colors);

        $this->assertSame(['red-1', 'red-2'], $names);
    }

    #[DataProvider('families')]
    public function testNamesTheColorsByTheirHue(string $hex, string $family): void
    {
        $palette = new PaletteBuilder()->build($this->inventory(['color' => [[$hex, 1]]]));

        $this->assertSame($family . '-1', $palette->colors[0]->name);
    }

    public function testRoundsSizesToClassesAndMergesTheOnesWhichShareOne(): void
    {
        $palette = new PaletteBuilder()->build($this->inventory([
            'fontSize' => [['14px', 4], ['14.4px', 1], ['14.67px', 2], ['20px', 1]],
        ]));

        $this->assertSame(
            ['ut-fs-14' => 5, 'ut-fs-15' => 2, 'ut-fs-20' => 1],
            array_combine(
                array_map(static fn(PaletteSize $size): string => $size->getClass(), $palette->sizes),
                array_map(static fn(PaletteSize $size): int => $size->getUsage(), $palette->sizes),
            ),
        );
    }

    public function testGroupsCloseSizes(): void
    {
        $palette = new PaletteBuilder()->build($this->inventory([
            'fontSize' => [['14px', 4], ['15px', 1], ['20px', 1]],
        ]), maxSizeDistance: 1);

        $this->assertSame([14, 20], array_map(static fn(PaletteSize $size): int => $size->pixels, $palette->sizes));
    }

    public function testReportsWhatThePaletteCannotExpress(): void
    {
        $palette = new PaletteBuilder()->build($this->inventory([
            'color'    => [['red', 2], ['#ff0000', 1]],
            'fontSize' => [['1.2em', 3]],
        ]));

        $this->assertSame(['color' => ['red' => 2], 'fontSize' => ['1.2em' => 3]], $palette->unmapped);
        $this->assertCount(1, $palette->colors);
    }

    public function testBuildsTheLegacyStylesMap(): void
    {
        $palette = new PaletteBuilder()->build($this->inventory([
            'color'    => [['#ff0000', 2], ['#fa0a0a', 1]],
            'fontSize' => [['14.67px', 1]],
        ]), 16);

        $this->assertSame([
            'color'           => ['#ff0000' => 'ut-color-red-1', '#fa0a0a' => 'ut-color-red-1'],
            'backgroundColor' => ['#ff0000' => 'ut-bg-red-1', '#fa0a0a' => 'ut-bg-red-1'],
            'fontSize'        => ['14.67px' => 'ut-fs-15'],
        ], $palette->toLegacyStyles());
    }

    public function testAnEmptyInventoryGivesAnEmptyPalette(): void
    {
        $palette = new PaletteBuilder()->build(new StyleInventory());

        $this->assertSame([], $palette->colors);
        $this->assertSame([], $palette->sizes);
    }

    /**
     * @param array<string, list<array{string, int}>> $values Property => value and number of contents using it
     */
    private function inventory(array $values): StyleInventory
    {
        $inventory = new StyleInventory();
        $contents = [];

        foreach ($values as $property => $counted) {
            foreach ($counted as [$value, $count]) {
                for ($i = 0; $i < $count; ++$i) {
                    $contents[$i][$property][] = $value;
                }
            }
        }

        foreach ($contents as $inlineStyles) {
            $inventory->add(
                new RoundTripResult(
                    new AuditedContent('s', 'content', '1', null, '', '<p>x</p>'),
                    '<p>x</p>',
                    false,
                    [],
                    null,
                    $inlineStyles,
                ),
            );
        }

        return $inventory;
    }
}
