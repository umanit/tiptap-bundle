<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Builder\DefaultToolsBuilder;
use Umanit\TiptapBundle\Builder\ToolsBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\Provider\ToolProvider;
use Umanit\TiptapBundle\Tests\Fixtures\CustomToolsBuilder;

#[CoversClass(ToolProvider::class)]
#[CoversClass(DefaultToolsBuilder::class)]
final class ToolProviderTest extends TestCase
{
    public function testToolsOfEveryBuilderAreAvailableByName(): void
    {
        $tools = new ToolProvider([new CustomToolsBuilder(), new DefaultToolsBuilder()]);

        $this->assertSame('toggleHighlight', $tools->getTool('highlight')->command);
        $this->assertSame('toggleBold', $tools->getTool('bold')->command);
    }

    public function testTheFirstBuilderWinsWhenANameIsListedTwice(): void
    {
        $replacement = new class implements ToolsBuilderInterface {
            public function build(): iterable
            {
                yield new Tool('bold', 'Strong', command: 'toggleStrong');
            }
        };

        $tools = new ToolProvider([$replacement, new DefaultToolsBuilder()]);

        $this->assertSame('toggleStrong', $tools->getTool('bold')->command);
    }

    public function testUnknownToolIsRejectedWithTheAvailableOnes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tool "nope" does not exist.*bold/');

        new ToolProvider([new DefaultToolsBuilder()])->getTool('nope');
    }

    public function testDefaultToolsAreUniqueAndComplete(): void
    {
        $names = array_map(static fn(Tool $tool): string => $tool->name, [...new DefaultToolsBuilder()->build()]);

        $this->assertSame($names, array_values(array_unique($names)));
        $this->assertContains('heading2', $names);
        $this->assertContains('source', $names);
    }
}
