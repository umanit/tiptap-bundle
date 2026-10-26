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
use Umanit\TiptapBundle\Tests\Fixtures\Tools;
use Umanit\TiptapBundle\Twig\Components\Color;
use Umanit\TiptapBundle\Twig\Components\ColorType;
use Umanit\TiptapBundle\Twig\Components\Editor;
use Umanit\TiptapBundle\Twig\Components\Styles;
use Umanit\TiptapBundle\Twig\Components\Toolbar;

#[CoversClass(Color::class)]
#[CoversClass(Editor::class)]
#[CoversClass(Styles::class)]
#[CoversClass(Toolbar::class)]
final class ComponentsTest extends TestCase
{
    public function testEditorDetectsToolbarTools(): void
    {
        $provider = new ToolbarProvider([
            new DefaultToolbarBuilder(Tools::provider()),
            new CompactToolbarBuilder(Tools::provider()),
        ]);

        $full = new Editor($provider);
        $this->assertTrue($full->hasTableTool());
        $this->assertTrue($full->hasImageTool());
        $this->assertTrue($full->hasLinkTool());
        $this->assertTrue($full->hasTemplateTool());
        $this->assertTrue($full->hasAnchorTool());

        $compact = new Editor($provider);
        $compact->toolbar = 'compact';
        $this->assertFalse($compact->hasTableTool());
        $this->assertFalse($compact->hasImageTool());
        $this->assertFalse($compact->hasLinkTool());
        $this->assertFalse($compact->hasTemplateTool());
        $this->assertFalse($compact->hasAnchorTool());
    }

    public function testEditorFileManagerIsOptional(): void
    {
        $editor = new Editor(new ToolbarProvider([]));

        $this->assertFalse($editor->hasFileManager());

        $editor->fileManagerUrl = '';
        $this->assertFalse($editor->hasFileManager());

        $editor->fileManagerUrl = '/files';
        $this->assertTrue($editor->hasFileManager());
    }

    public function testColorComponentMapsTypeToCommandAndClass(): void
    {
        $provider = new ColorsSetProvider([new DefaultColorsSetBuilder()]);
        $color = new ColorDTO('wysiwyg.color.red', 'red');

        $text = new Color($provider);
        $text->type = ColorType::Text;
        $this->assertSame('setTextColor', $text->command());
        $this->assertSame('unsetTextColor', $text->resetCommand());
        $this->assertSame('A', $text->btnLabel());
        $this->assertSame('ut-color-red', $text->getColorClass($color));
        $this->assertCount(13, $text->colors());

        $background = new Color($provider);
        $background->type = ColorType::Background;
        $this->assertSame('setTextBgColor', $background->command());
        $this->assertSame('unsetTextBgColor', $background->resetCommand());
        $this->assertSame('', $background->btnLabel());
        $this->assertSame('ut-bg-red', $background->getColorClass($color));
    }

    public function testStylesAndToolbarComponentsResolveTheirSet(): void
    {
        $styles = new Styles(new StylesSetProvider([new DefaultStylesSetsBuilder()]));
        $this->assertCount(3, $styles->getStylesSet());

        $toolbar = new Toolbar(new ToolbarProvider([new CompactToolbarBuilder(Tools::provider())]));
        $toolbar->name = 'compact';
        $this->assertTrue($toolbar->getToolbar()->hasTool('bold'));
    }
}
