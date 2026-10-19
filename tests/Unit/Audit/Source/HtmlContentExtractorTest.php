<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Audit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;

#[CoversClass(HtmlContentExtractor::class)]
#[CoversClass(AuditedContent::class)]
final class HtmlContentExtractorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideContents(): iterable
    {
        yield 'paragraph' => ['<p>Hello</p>', true];
        yield 'orphan tag' => ['before<br/>after', true];
        yield 'heading with attribute' => ['<h2 class="a">x</h2>', true];
        yield 'plain text' => ['Hello', false];
        yield 'comparison' => ['a < b and c > d', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('provideContents')]
    public function testLooksLikeHtml(string $content, bool $expected): void
    {
        $this->assertSame($expected, new HtmlContentExtractor()->looksLikeHtml($content));
    }

    public function testSingleValue(): void
    {
        $extractor = new HtmlContentExtractor();

        $extracted = $extractor->extract('parameter.value', 'content', '7', 'login.title', '<p>x</p>');
        $contents = iterator_to_array($extracted, false);

        $this->assertCount(1, $contents);
        $this->assertSame('<p>x</p>', $contents[0]->html);
        $this->assertSame('parameter.value', $contents[0]->source);
        $this->assertSame('content', $contents[0]->group);
        $this->assertSame('7 (login.title)', $contents[0]->describe());
        $this->assertSame([], iterator_to_array($extractor->extract('s', 'g', '8', null, 'plain text')));
    }

    public function testLocaleMapYieldsOneContentPerLocaleWithHtml(): void
    {
        $value = ['fr' => '<p>Bonjour</p>', 'en' => 'Hello', 'de' => null, 'es' => '<p>Hola</p>'];

        $extracted = new HtmlContentExtractor()->extract('text.text', 'content', '42', null, $value);
        $contents = iterator_to_array($extracted, false);

        $paths = array_map(static fn(AuditedContent $content): string => $content->path, $contents);

        $this->assertSame(['fr', 'es'], $paths);
        $this->assertSame('42/fr', $contents[0]->describe());
    }

    public function testNestedStructureIsWalkedAtAnyDepth(): void
    {
        $value = [
            'title'  => 'Title',
            'text'   => ['fr' => '<p>Bonjour</p>', 'en' => '<p>Hello</p>'],
            'items'  => [['body' => '<ul><li>x</li></ul>'], ['body' => 'plain']],
            'layout' => 12,
        ];

        $extracted = new HtmlContentExtractor()->extract('page.content', 'content', '3', null, $value);
        $contents = iterator_to_array($extracted, false);
        $paths = array_map(static fn(AuditedContent $content): string => $content->path, $contents);

        $this->assertSame(['text.fr', 'text.en', 'items.0.body'], $paths);
        $this->assertSame('<p>Bonjour</p>', $contents[0]->html);
    }
}
