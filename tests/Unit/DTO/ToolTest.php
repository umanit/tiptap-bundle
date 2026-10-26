<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\DTO\Tool;

#[CoversClass(Tool::class)]
final class ToolTest extends TestCase
{
    public function testButtonToolRunsACommand(): void
    {
        $tool = new Tool(
            'highlight',
            'Highlight',
            command: 'toggleHighlight',
            args: ['color' => 'yellow'],
            active: ['name' => 'highlight'],
        );

        $this->assertSame('highlight', (string) $tool);
        $this->assertSame(['color' => 'yellow'], $tool->args);
        $this->assertSame('UmanitTiptapBundle', $tool->translationDomain);
    }

    public function testToolNeedsANameAndATitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tool('', 'Highlight', command: 'toggleHighlight');
    }

    public function testToolNeedsATitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tool('highlight', '', command: 'toggleHighlight');
    }

    public function testToolHasACommandOrAComponent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not both nor none');

        new Tool('highlight', 'Highlight');
    }

    public function testToolCannotHaveBothACommandAndAComponent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not both nor none');

        new Tool('highlight', 'Highlight', command: 'toggleHighlight', component: 'App:Highlight');
    }

    public function testActiveStateNeedsACommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no command');

        new Tool('palette', 'Palette', component: 'App:Palette', active: ['name' => 'palette']);
    }

    public function testActiveStateRefusesUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown keys: nope');

        new Tool('highlight', 'Highlight', command: 'toggleHighlight', active: ['name' => 'highlight', 'nope' => 1]);
    }

    public function testComponentPropsComeFromTheToolAndFromTheEditor(): void
    {
        $tool = new Tool(
            'palette',
            'Palette',
            icon: 'app:palette',
            component: 'App:Palette',
            props: ['type' => 'text'],
            context: ['name' => 'colorsSet'],
        );

        $this->assertSame(
            ['title' => 'Palette', 'icon' => 'app:palette', 'name' => 'brand', 'type' => 'text'],
            $tool->propsFor(['colorsSet' => 'brand', 'stylesSet' => 'other']),
        );
    }

    public function testComponentPropsFailWhenTheEditorDoesNotProvideTheContext(): void
    {
        $tool = new Tool('palette', 'Palette', component: 'App:Palette', context: ['name' => 'nope']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reads "nope"');

        $tool->propsFor(['colorsSet' => 'brand']);
    }
}
