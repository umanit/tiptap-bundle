<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Builder\DefaultColorsSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultStylesSetsBuilder;
use Umanit\TiptapBundle\Builder\DefaultTemplatesSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultToolbarBuilder;
use Umanit\TiptapBundle\Builder\StylesSetsBuilderInterface;
use Umanit\TiptapBundle\DTO\ColorsSet;
use Umanit\TiptapBundle\DTO\StylesSets;
use Umanit\TiptapBundle\DTO\TemplatesSet;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\Provider\ColorsSetProvider;
use Umanit\TiptapBundle\Provider\StylesSetProvider;
use Umanit\TiptapBundle\Provider\TemplatesSetProvider;
use Umanit\TiptapBundle\Provider\ToolbarProvider;

#[CoversClass(ColorsSetProvider::class)]
#[CoversClass(StylesSetProvider::class)]
#[CoversClass(TemplatesSetProvider::class)]
#[CoversClass(ToolbarProvider::class)]
final class ProvidersTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function provideUnknownNames(): iterable
    {
        $toolbars = new ToolbarProvider([new DefaultToolbarBuilder()]);
        $styles = new StylesSetProvider([new DefaultStylesSetsBuilder()]);
        $templates = new TemplatesSetProvider([new DefaultTemplatesSetBuilder()]);
        $colors = new ColorsSetProvider([new DefaultColorsSetBuilder()]);

        yield 'toolbar' => [
            static fn(): Toolbar => $toolbars->getToolbar('nope'),
            'The toolbar "nope" does not exist.',
        ];
        yield 'styles set' => [
            static fn(): StylesSets => $styles->getStylesSet('nope'),
            'The styles set "nope" does not exist.',
        ];
        yield 'templates set' => [
            static fn(): TemplatesSet => $templates->getTemplatesSet('nope'),
            'The templates set "nope" does not exist.',
        ];
        yield 'colors set' => [
            static fn(): ColorsSet => $colors->getColorsSet('nope'),
            'The colors set "nope" does not exist.',
        ];
    }

    /**
     * @param callable(): mixed $call
     */
    #[DataProvider('provideUnknownNames')]
    public function testUnknownNameIsRejected(callable $call, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $call();
    }

    public function testProvidersAcceptAnyIterable(): void
    {
        $provider = new StylesSetProvider(new \ArrayIterator([new DefaultStylesSetsBuilder()]));

        $this->assertCount(3, $provider->getStylesSet('default'));
    }

    public function testFirstBuilderWinsWhenNamesCollide(): void
    {
        $first = new class implements StylesSetsBuilderInterface {
            public static function getDefaultIndexName(): string
            {
                return 'default';
            }

            public function build(): StylesSets
            {
                return new StylesSets([]);
            }
        };

        $provider = new StylesSetProvider([$first, new DefaultStylesSetsBuilder()]);

        $this->assertCount(0, $provider->getStylesSet('default'));
    }
}
