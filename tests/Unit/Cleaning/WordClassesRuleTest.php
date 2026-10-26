<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Cleaning;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\HTMLElement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Cleaning\Rule\WordClassesRule;

/**
 * The cases are shared with `assets/tests/html-cleaner.test.js`, which replays them on the editor: both drop the same
 * classes of Word.
 */
#[CoversClass(WordClassesRule::class)]
final class WordClassesRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideCases(): iterable
    {
        /** @var array{cases: list<array{classes: string, kept: string|null}>} $shared */
        $shared = \json_decode(
            (string) \file_get_contents(__DIR__ . '/../../Fixtures/Cleaning/word-classes-cases.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        foreach ($shared['cases'] as $case) {
            yield $case['classes'] => [$case['classes'], $case['kept']];
        }
    }

    #[DataProvider('provideCases')]
    public function testItDropsTheSameClassesAsTheEditor(string $classes, ?string $kept): void
    {
        $document = HTMLDocument::createFromString(\sprintf('<body><p class="%s">x</p></body>', $classes));
        $body = $document->body;
        $paragraph = $document->querySelector('p');

        $this->assertInstanceOf(HTMLElement::class, $body);
        $this->assertInstanceOf(Element::class, $paragraph);

        new WordClassesRule()->apply($body);

        $this->assertSame($kept, $paragraph->getAttribute('class'));
    }
}
