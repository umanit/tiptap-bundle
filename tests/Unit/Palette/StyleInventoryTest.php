<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Palette;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Palette\StyleInventory;

#[CoversClass(StyleInventory::class)]
final class StyleInventoryTest extends TestCase
{
    public function testCountsContentsByValueMostUsedFirst(): void
    {
        $inventory = new StyleInventory();
        $inventory->add($this->resultWith(['color' => ['#ff0000', '#0000ff'], 'fontSize' => ['14px']]));
        $inventory->add($this->resultWith(['color' => ['#ff0000', '#ff0000'], 'backgroundColor' => ['#ffff00']]));
        $inventory->add($this->resultWith(['color' => ['#00ff00']]));
        $inventory->add($this->resultWith([]));

        $this->assertSame(4, $inventory->getContentCount());
        $this->assertSame(['#ff0000' => 2, '#0000ff' => 1, '#00ff00' => 1], $inventory->get('color'));
        $this->assertSame(['#ffff00' => 1], $inventory->get('backgroundColor'));
        $this->assertSame(['14px' => 1], $inventory->get('fontSize'));
        $this->assertSame([], $inventory->get('unknown'));
    }

    /**
     * @param array<string, list<string>> $inlineStyles
     */
    private function resultWith(array $inlineStyles): RoundTripResult
    {
        return new RoundTripResult(
            new AuditedContent('article.body', 'content', '1', null, '', '<p>x</p>'),
            '<p>x</p>',
            false,
            [],
            null,
            $inlineStyles,
        );
    }
}
