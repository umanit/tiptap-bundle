<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Cleaning;

use Dom\HTMLElement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Cleaning\CleaningResult;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\HtmlCleaner;
use Umanit\TiptapBundle\Cleaning\Rule\EmptyInlineRule;
use Umanit\TiptapBundle\Cleaning\Rule\EmptyParagraphsRule;
use Umanit\TiptapBundle\Cleaning\Rule\FontTagRule;
use Umanit\TiptapBundle\Cleaning\Rule\ListValueRule;
use Umanit\TiptapBundle\Cleaning\Rule\NbspRunsRule;
use Umanit\TiptapBundle\Cleaning\Rule\NestedSpansRule;
use Umanit\TiptapBundle\Cleaning\Rule\SmallIndentRule;
use Umanit\TiptapBundle\Cleaning\Rule\TableLayoutRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordClassesRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordLanguageRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordStylesRule;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

#[CoversClass(HtmlCleaner::class)]
#[CoversClass(CleaningResult::class)]
#[CoversClass(StyleDeclarations::class)]
#[CoversClass(WordLanguageRule::class)]
#[CoversClass(WordClassesRule::class)]
#[CoversClass(FontTagRule::class)]
#[CoversClass(WordStylesRule::class)]
#[CoversClass(TableLayoutRule::class)]
#[CoversClass(SmallIndentRule::class)]
#[CoversClass(ListValueRule::class)]
#[CoversClass(NestedSpansRule::class)]
#[CoversClass(EmptyInlineRule::class)]
#[CoversClass(NbspRunsRule::class)]
#[CoversClass(EmptyParagraphsRule::class)]
final class HtmlCleanerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideCleanedContents(): iterable
    {
        yield 'the style wrappers of a pasted paragraph merge into the one which counts' => [
            '<p style="text-align:justify;"><span style="color:#000000;"><span style="font-size:11pt;">'
            . '<span style="line-height:107%;"><span style="font-family:Calibri, sans-serif;">'
            . '<span lang="en-us" style="font-size:12pt;" xml:lang="en-us">Hello</span>'
            . '</span></span></span></span></p>',
            '<p style="text-align:justify;"><span style="font-size:12pt;">Hello</span></p>',
            ['word-language', 'word-styles', 'nested-spans'],
        ];

        yield 'a color and a size are not Word leftovers' => [
            '<h2 style="margin-bottom:11px;"><span style="font-size:18px;"><b>'
            . '<span style="color:#3dcd58;">Policy</span></b></span></h2>',
            '<h2><span style="font-size:18px;"><b><span style="color:#3dcd58;">Policy</span></b></span></h2>',
            ['word-styles'],
        ];

        yield 'black is the default once nothing else colors the text' => [
            '<p><span style="color:#ff0000;"><span style="color:#000000;">a</span></span>'
            . ' <span style="color:#000;">b</span></p>',
            '<p>a b</p>',
            ['word-styles', 'nested-spans'],
        ];

        yield 'a link loses the look of Word, not its address' => [
            '<p><a href="mailto:a@b.c" style="color:#0563c1;text-decoration:underline;">'
            . '<span style="color:#000000;">a@b.c</span></a></p>',
            '<p><a href="mailto:a@b.c">a@b.c</a></p>',
            ['word-styles', 'nested-spans'],
        ];

        yield 'a table keeps its structure and loses its layout' => [
            '<table class="MsoTableGrid" style="width:510px;border-collapse:collapse;border:none;"'
            . ' width="510" border="1" cellpadding="1" cellspacing="1"><tbody><tr>'
            . '<td colspan="2" style="border-bottom:1px solid #000000;width:236px;padding:0cm 7px;height:23px;'
            . 'text-align:center;" valign="top"><p>a</p></td></tr></tbody></table>',
            '<table border="1"><tbody><tr><td colspan="2" style="width:236px;text-align:center;"><p>a</p></td></tr>'
            . '</tbody></table>',
            ['word-classes', 'table-layout'],
        ];

        yield 'the width of a cell in pixels stays, any other unit goes' => [
            '<table><tbody><tr><td width="120" style="width:274px;height:20px">a</td>'
            . '<th width="50%" style="width:30%">b</th></tr></tbody></table>',
            '<table><tbody><tr><td width="120" style="width:274px;">a</td><th>b</th></tr></tbody></table>',
            ['table-layout'],
        ];

        yield 'a border in pixels stays, any other value goes' => [
            '<table border="0"><tbody><tr><td>a</td></tr></tbody></table>'
            . '<table border="2"><tbody><tr><td>b</td></tr></tbody></table>'
            . '<table border="1px"><tbody><tr><td>c</td></tr></tbody></table>',
            '<table border="0"><tbody><tr><td>a</td></tr></tbody></table>'
            . '<table border="2"><tbody><tr><td>b</td></tr></tbody></table>'
            . '<table><tbody><tr><td>c</td></tr></tbody></table>',
            ['table-layout'],
        ];

        yield 'a table centered or aligned to the right keeps its alignment, a left one loses it' => [
            '<table align="center" width="50%"><tbody><tr><td>a</td></tr></tbody></table>'
            . '<table align="RIGHT"><tbody><tr><td>b</td></tr></tbody></table>'
            . '<table align="left"><tbody><tr><td>c</td></tr></tbody></table>',
            '<table align="center"><tbody><tr><td>a</td></tr></tbody></table>'
            . '<table align="RIGHT"><tbody><tr><td>b</td></tr></tbody></table>'
            . '<table><tbody><tr><td>c</td></tr></tbody></table>',
            ['table-layout'],
        ];

        yield 'the classes of the application stay' => [
            '<div class="WordSection1 keydates"><p class="MsoNormal primary-banner">a</p></div>',
            '<div class="keydates"><p class="primary-banner">a</p></div>',
            ['word-classes'],
        ];

        yield 'the section div of Word goes away' => [
            '<div class="WordSection1"><p>a</p></div>',
            '<p>a</p>',
            ['word-classes'],
        ];

        yield 'a font tag keeps its color only' => [
            '<p><font face="Arial, Helvetica, sans-serif" color="#595959">a</font> <font face="Arial">b</font></p>',
            '<p><span style="color:#595959;">a</span> b</p>',
            ['font-tag'],
        ];

        yield 'the value of a bulleted item goes, the one of a numbered item stays' => [
            '<ul><li value="17">a</li></ul><ol><li value="3">b</li></ol>',
            '<ul><li>a</li></ul><ol><li value="3">b</li></ol>',
            ['list-value'],
        ];

        yield 'only a small margin is the offset of Word' => [
            '<ul><li style="margin-left:8px;">a</li></ul><p style="margin-left:40px;">b</p>'
            . '<p style="margin-left:6pt;">c</p>',
            '<ul><li>a</li></ul><p style="margin-left:40px;">b</p><p>c</p>',
            ['small-indent'],
        ];

        yield 'formatting around a space only is unwrapped, a hidden one removed' => [
            '<p><span style="display:none;">&nbsp;</span>one<strong> </strong>two<em></em><b>three</b></p>',
            '<p>one two<b>three</b></p>',
            ['empty-inline'],
        ];

        yield 'non-breaking spaces which indent or space out' => [
            '<p>&nbsp;&nbsp;Name:&nbsp;&nbsp;&nbsp;value&nbsp;&nbsp;</p><p>10&nbsp;%</p><p>a&nbsp; b</p>',
            '<p>Name: value</p><p>10&nbsp;%</p><p>a&nbsp; b</p>',
            ['nbsp-runs'],
        ];

        yield 'trailing non-breaking spaces inside a format' => [
            '<ul><li><strong>Payroll&nbsp;&nbsp;&nbsp;</strong></li></ul>',
            '<ul><li><strong>Payroll</strong></li></ul>',
            ['nbsp-runs'],
        ];

        yield 'empty paragraphs at the ends and in runs' => [
            '<p>&nbsp;</p><p>a</p><p>&nbsp;</p><p><br></p><p>b</p><p> </p>',
            '<p>a</p><p>&nbsp;</p><p>b</p>',
            ['empty-paragraphs'],
        ];
    }

    /**
     * @param list<string> $rules The rules which must have worked
     */
    #[DataProvider('provideCleanedContents')]
    public function testItCleansTheContent(string $html, string $expected, array $rules): void
    {
        $result = $this->cleaner()->clean($html);

        $this->assertFalse($result->skipped);
        $this->assertSame($expected, $result->html);
        $this->assertEqualsCanonicalizing($rules, \array_keys($result->changes));
    }

    #[DataProvider('provideCleanedContents')]
    public function testCleaningTwiceIsCleaningOnce(string $html): void
    {
        $once = $this->cleaner()->clean($html);
        $twice = $this->cleaner()->clean($once->html);

        $this->assertFalse($twice->isChanged());
        $this->assertSame($once->html, $twice->html);
    }

    public function testAContentWithNothingToCleanComesBackUntouched(): void
    {
        // Not even re-serialized: the entities, the tabs and the self-closing tags are the ones it came with
        $html = "<p>caf&eacute; <br />\n\t<em>x</em></p>";

        $result = $this->cleaner()->clean($html);

        $this->assertFalse($result->isChanged());
        $this->assertFalse($result->skipped);
        $this->assertSame($html, $result->html);
    }

    public function testTheRulesCanBeChosen(): void
    {
        $html = '<p style="line-height:107%;"><span lang="fr">a</span>&nbsp;&nbsp;</p>';

        $only = $this->cleaner()->clean($html, ['word-language']);
        $skipping = $this->cleaner()->clean($html, [], ['word-language']);

        $this->assertSame(['word-language'], \array_keys($only->changes));
        $this->assertSame(['word-styles', 'nbsp-runs'], \array_keys($skipping->changes));
    }

    public function testTheTextIsNeverChanged(): void
    {
        // A rule which loses text must not get its way
        $cleaner = new HtmlCleaner([
            new class implements CleaningRuleInterface {
                public function getName(): string
                {
                    return 'greedy';
                }

                public function getDescription(): string
                {
                    return 'Removes a word';
                }

                public function apply(HTMLElement $root): int
                {
                    $root->innerHTML = '<p>a</p>';

                    return 1;
                }
            },
        ]);

        $result = $cleaner->clean('<p>a b</p>');

        $this->assertTrue($result->skipped);
        $this->assertFalse($result->isChanged());
        $this->assertSame('<p>a b</p>', $result->html);
    }

    public function testBlackOverAnotherColorOfTheSameTextIsKept(): void
    {
        $html = '<p><span style="color:#ff0000;">red <span style="color:#000000;">black</span></span></p>';

        $this->assertFalse($this->cleaner()->clean($html)->isChanged());
    }

    public function testTheRulesAreListedInTheOrderTheyRun(): void
    {
        $names = \array_map(static fn($rule): string => $rule->getName(), $this->cleaner()->getRules());

        $this->assertSame(
            [
                'word-language',
                'word-classes',
                'font-tag',
                'word-styles',
                'table-layout',
                'small-indent',
                'list-value',
                'nested-spans',
                'empty-inline',
                'nbsp-runs',
                'empty-paragraphs',
            ],
            $names,
        );
    }

    public function testStyleDeclarationsAreParsedAndWrittenLikeCkeditorDoes(): void
    {
        $this->assertSame(
            ['color' => 'red', 'background' => 'url(data:image/png;base64,AA)'],
            StyleDeclarations::parse('Color: red ; background:url(data:image/png;base64,AA);;oops'),
        );
    }

    private function cleaner(): HtmlCleaner
    {
        return new HtmlCleaner([
            new WordLanguageRule(),
            new WordClassesRule(),
            new FontTagRule(),
            new WordStylesRule(),
            new TableLayoutRule(),
            new SmallIndentRule(),
            new ListValueRule(),
            new NestedSpansRule(),
            new EmptyInlineRule(),
            new NbspRunsRule(),
            new EmptyParagraphsRule(),
        ]);
    }
}
