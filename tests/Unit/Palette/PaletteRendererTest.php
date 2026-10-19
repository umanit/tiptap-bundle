<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Palette;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Palette\Palette;
use Umanit\TiptapBundle\Palette\PaletteColor;
use Umanit\TiptapBundle\Palette\PaletteRenderer;
use Umanit\TiptapBundle\Palette\PaletteSize;

#[CoversClass(PaletteRenderer::class)]
final class PaletteRendererTest extends TestCase
{
    public function testRendersAValidColorsSetBuilder(): void
    {
        $code = new PaletteRenderer()->renderBuilder($this->palette(), 'App\Wysiwyg\LegacyColorsSetBuilder', 'legacy');

        $this->assertStringContainsString("namespace App\\Wysiwyg;\n", $code);
        $this->assertStringContainsString(
            "new Color('Red 1', 'red-1'), // #ff0000\n            new Color('Blue 1'",
            $code,
        );
        $this->assertStringContainsString("return 'legacy';", $code);

        $file = tempnam(sys_get_temp_dir(), 'palette');
        file_put_contents($file, $code);
        $lint = (string) shell_exec(\sprintf('php -l %s 2>&1', escapeshellarg($file)));
        $this->assertStringContainsString('No syntax errors', $lint);
        unlink($file);
    }

    public function testRendersABuilderWithoutNamespace(): void
    {
        $code = new PaletteRenderer()->renderBuilder($this->palette(), 'LegacyColorsSetBuilder');

        $this->assertStringNotContainsString('namespace', $code);
        $this->assertStringContainsString('final readonly class LegacyColorsSetBuilder', $code);
    }

    public function testRejectsAnInvalidClassName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PaletteRenderer()->renderBuilder($this->palette(), 'App\Bad Name');
    }

    public function testRendersTheClasses(): void
    {
        $css = new PaletteRenderer()->renderCss($this->palette());

        $this->assertStringContainsString('--ut-color-red-1: #ff0000;', $css);
        $this->assertStringContainsString('.ut-color-blue-1 { color: var(--ut-color-blue-1); }', $css);
        $this->assertStringContainsString('.ut-bg-red-1 { background-color: var(--ut-color-red-1) !important; }', $css);
        $this->assertStringContainsString('.ut-fs-14 { font-size: 14px; }', $css);
    }

    public function testRendersTheLegacyStylesAsJson(): void
    {
        $json = new PaletteRenderer()->renderLegacyStyles($this->palette());

        $this->assertSame($this->palette()->toLegacyStyles(), json_decode($json, true, 512, \JSON_THROW_ON_ERROR));
    }

    private function palette(): Palette
    {
        return new Palette(
            [
                new PaletteColor('red-1', '#ff0000', ['#ff0000' => 2]),
                new PaletteColor('blue-1', '#0000ff', ['#0000ff' => 1]),
            ],
            [new PaletteSize(14, ['14px' => 3])],
        );
    }
}
