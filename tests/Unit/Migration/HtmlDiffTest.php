<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Migration\HtmlDiff;

#[CoversClass(HtmlDiff::class)]
final class HtmlDiffTest extends TestCase
{
    public function testAChangedAttributeShowsOnItsOwnTag(): void
    {
        $diff = new HtmlDiff()->diff(
            '<p>a</p><p><img alt="" src="a.jpg" style="width:50%;" /></p><p>b</p>',
            '<p>a</p><p><img alt="" src="a.jpg"></p><p>b</p>',
            1,
        );

        $this->assertSame(
            [
                ['…', ''],
                [' ', '<p>'],
                ['-', '<img alt="" src="a.jpg" style="width:50%;" />'],
                ['+', '<img alt="" src="a.jpg">'],
                [' ', '</p>'],
                ['…', ''],
            ],
            $diff,
        );
    }

    public function testNothingChangesNothingShows(): void
    {
        $this->assertSame([['…', '']], new HtmlDiff()->diff('<p>a</p><p>b</p>', '<p>a</p>' . "\n" . '<p>b</p>', 1));
    }
}
