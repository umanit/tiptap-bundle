<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Builder\DefaultColorsSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultStylesSetsBuilder;
use Umanit\TiptapBundle\Builder\DefaultToolbarBuilder;
use Umanit\TiptapBundle\DTO\Color as ColorDTO;
use Umanit\TiptapBundle\Provider\ColorsSetProvider;
use Umanit\TiptapBundle\Provider\StylesSetProvider;
use Umanit\TiptapBundle\Provider\ToolbarProvider;
use Umanit\TiptapBundle\Tests\Fixtures\CompactToolbarBuilder;
use Umanit\TiptapBundle\Twig\Components\Color;
use Umanit\TiptapBundle\Twig\Components\ColorType;
use Umanit\TiptapBundle\Twig\Components\Editor;
use Umanit\TiptapBundle\Twig\Components\Styles;
use Umanit\TiptapBundle\Twig\Components\Toolbar;

#[CoversClass(Color::class)]
#[CoversClass(Editor::class)]
#[CoversClass(Styles::class)]
#[CoversClass(Toolbar::class)]
class ComponentsTest extends TestCase
{
    public function testEditorDetectsToolbarTools(): void
    {
        $provider = new ToolbarProvider([new DefaultToolbarBuilder(), new CompactToolbarBuilder()]);

        $full = new Editor($provider);
        self::assertTrue($full->hasTableTool());
        self::assertTrue($full->hasImageTool());
        self::assertTrue($full->hasLinkTool());
        self::assertTrue($full->hasTemplateTool());
        self::assertTrue($full->hasAnchorTool());

        $compact = new Editor($provider);
        $compact->toolbar = 'compact';
        self::assertFalse($compact->hasTableTool());
        self::assertFalse($compact->hasImageTool());
        self::assertFalse($compact->hasLinkTool());
        self::assertFalse($compact->hasTemplateTool());
        self::assertFalse($compact->hasAnchorTool());
    }

    public function testEditorFileManagerIsOptional(): void
    {
        $editor = new Editor(new ToolbarProvider([]));

        self::assertFalse($editor->hasFileManager());

        $editor->fileManagerUrl = '';
        self::assertFalse($editor->hasFileManager());

        $editor->fileManagerUrl = '/files';
        self::assertTrue($editor->hasFileManager());
    }

    public function testColorComponentMapsTypeToCommandAndClass(): void
    {
        $provider = new ColorsSetProvider([new DefaultColorsSetBuilder()]);
        $color = new ColorDTO('wysiwyg.color.red', 'red');

        $text = new Color($provider);
        $text->type = ColorType::Text;
        self::assertSame('setTextColor', $text->command());
        self::assertSame('A', $text->btnLabel());
        self::assertSame('ut-color-red', $text->getColorClass($color));
        self::assertCount(13, $text->colors());

        $background = new Color($provider);
        $background->type = ColorType::Background;
        self::assertSame('setTextBgColor', $background->command());
        self::assertSame('', $background->btnLabel());
        self::assertSame('ut-bg-red', $background->getColorClass($color));
    }

    public function testStylesAndToolbarComponentsResolveTheirSet(): void
    {
        $styles = new Styles(new StylesSetProvider([new DefaultStylesSetsBuilder()]));
        self::assertCount(3, $styles->getStylesSet());

        $toolbar = new Toolbar(new ToolbarProvider([new CompactToolbarBuilder()]));
        $toolbar->name = 'compact';
        self::assertTrue($toolbar->getToolbar()->hasTool('bold'));
    }
}
