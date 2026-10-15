<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\DTO\Color;

#[CoversClass(Color::class)]
final class ColorTest extends TestCase
{
    public function testCssClasses(): void
    {
        $color = new Color('wysiwyg.color.red', 'red');

        $this->assertSame('ut-color-red', $color->getAsTextColorClass());
        $this->assertSame('ut-bg-red', $color->getAsBackgroundColorClass());
    }
}
