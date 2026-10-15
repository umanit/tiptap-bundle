<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;

#[CoversClass(Toolbar::class)]
#[CoversClass(ToolbarSection::class)]
#[CoversClass(Tool::class)]
class ToolbarTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSpecificTools(): iterable
    {
        yield 'table' => ['table', 'hasTableTool'];
        yield 'image' => ['image', 'hasImageTool'];
        yield 'link' => ['link', 'hasLinkTool'];
        yield 'template' => ['template', 'hasTemplateTool'];
        yield 'anchor' => ['anchor', 'hasAnchorTool'];
    }

    #[DataProvider('provideSpecificTools')]
    public function testSpecificToolDetection(string $tool, string $method): void
    {
        $with = new Toolbar([new ToolbarSection([new Tool('bold')]), new ToolbarSection([new Tool($tool)])]);
        $without = new Toolbar([new ToolbarSection([new Tool('bold')])]);

        self::assertTrue($with->{$method}());
        self::assertFalse($without->{$method}());
    }

    public function testHasToolOnEmptyToolbar(): void
    {
        self::assertFalse(new Toolbar([])->hasTool('bold'));
    }

    public function testToolIsStringable(): void
    {
        self::assertSame('bold', (string) new Tool('bold'));
    }
}
