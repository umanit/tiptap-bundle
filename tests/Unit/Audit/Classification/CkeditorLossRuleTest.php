<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Audit\Classification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossCategory;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\Classification\LossRuleInterface;
use Umanit\TiptapBundle\Audit\Classification\LossStatus;

#[CoversClass(CkeditorLossRule::class)]
#[CoversClass(LossClassifier::class)]
#[CoversClass(LossCategory::class)]
#[CoversClass(LossStatus::class)]
final class CkeditorLossRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, LossStatus}>
     */
    public static function provideSignals(): iterable
    {
        yield 'color' => ['style:color', 'color', LossStatus::Convertible];
        yield 'background' => ['style:background-color', 'color', LossStatus::Convertible];
        yield 'size' => ['style:font-size', 'font_size', LossStatus::Convertible];
        yield 'alignment' => ['style:text-align', 'alignment', LossStatus::Decision];
        yield 'indentation' => ['style:margin-left', 'indent', LossStatus::Decision];
        yield 'font family' => ['style:font-family', 'font_family', LossStatus::Decision];
        yield 'font tag' => ['attr:font@color', 'font_family', LossStatus::Decision];
        yield 'table borders' => ['style:border*', 'table', LossStatus::Decision];
        yield 'cellpadding' => ['attr:table@cellpadding', 'table', LossStatus::Decision];
        yield 'width' => ['style:width', 'table', LossStatus::Decision];
        yield 'anchor' => ['attr:a@id', 'anchor', LossStatus::Decision];
        yield 'div' => ['tag:div', 'block', LossStatus::Decision];
        yield 'class' => ['class:WordSection1', 'block', LossStatus::Decision];
        yield 'language' => ['attr:span@lang', 'metadata', LossStatus::Acceptable];
        yield 'xml:lang' => ['attr:span@xml:lang', 'metadata', LossStatus::Acceptable];
        yield 'mso' => ['style:mso-*', 'metadata', LossStatus::Acceptable];
        yield 'line height' => ['style:line-height', 'typography', LossStatus::Decision];
        yield 'margin bottom' => ['style:margin-bottom', 'typography', LossStatus::Decision];
    }

    #[DataProvider('provideSignals')]
    public function testCategorize(string $signal, string $expectedKey, LossStatus $expectedStatus): void
    {
        $category = new CkeditorLossRule()->categorize($signal);

        $this->assertInstanceOf(LossCategory::class, $category);
        $this->assertSame($expectedKey, $category->key);
        $this->assertSame($expectedStatus, $category->status);
    }

    public function testUnknownSignalIsNotCategorized(): void
    {
        $this->assertNull(new CkeditorLossRule()->categorize('attr:li@value'));
    }

    public function testClassifierFallsBackToUnclassified(): void
    {
        $category = new LossClassifier([new CkeditorLossRule()])->classify('attr:li@value');

        $this->assertSame(LossClassifier::UNCLASSIFIED_KEY, $category->key);
        $this->assertSame(LossStatus::Unclassified, $category->status);
    }

    public function testFirstMatchingRuleWins(): void
    {
        $application = new class implements LossRuleInterface {
            public function categorize(string $signal): ?LossCategory
            {
                if ('style:color' !== $signal) {
                    return null;
                }

                return new LossCategory('brand', 'Brand color', LossStatus::Acceptable);
            }
        };

        $classifier = new LossClassifier([$application, new CkeditorLossRule()]);

        $this->assertSame('brand', $classifier->classify('style:color')->key);
        $this->assertSame('font_size', $classifier->classify('style:font-size')->key);
    }

    public function testStatusesHaveALabel(): void
    {
        foreach (LossStatus::cases() as $status) {
            $this->assertNotSame('', $status->getLabel());
        }
    }
}
