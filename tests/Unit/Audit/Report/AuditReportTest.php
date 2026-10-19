<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Audit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\Report\AuditReport;
use Umanit\TiptapBundle\Audit\RoundTrip\LostSignal;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;

#[CoversClass(AuditReport::class)]
#[CoversClass(RoundTripResult::class)]
#[CoversClass(LostSignal::class)]
final class AuditReportTest extends TestCase
{
    public function testCountsAContentOnceByCategory(): void
    {
        $report = $this->report();
        $report->add($this->roundTrip('1', ['style:color', 'style:background-color', 'style:font-size']));
        $report->add($this->roundTrip('2', ['style:color']));
        $report->add($this->roundTrip('3'));

        $data = $report->toArray();
        $source = $data['sources']['text.text'];

        $this->assertSame(['values' => 3, 'with_loss' => 2, 'text_changed' => 0, 'errors' => 0], $data['totals']);
        $this->assertSame('content', $source['group']);
        $this->assertSame(3, $source['total']);
        $this->assertSame(2, $source['with_loss']);

        $color = $source['categories'][0];
        $this->assertSame('color', $color['key']);
        $this->assertSame('convertible', $color['status']);
        $this->assertSame(2, $color['values'], 'Two color signals on one content count for one content');
        $this->assertSame(['style:color' => 2, 'style:background-color' => 1], $color['signals']);
        $this->assertSame(['1/fr', '2/fr'], $color['examples']);

        $this->assertSame('font_size', $source['categories'][1]['key']);
    }

    public function testKeepsOnlyTheFirstExamples(): void
    {
        $report = $this->report(2);

        foreach (['1', '2', '3', '4'] as $id) {
            $report->add($this->roundTrip($id, ['style:color'], true));
        }

        $source = $report->toArray()['sources']['text.text'];

        $this->assertSame(['1/fr', '2/fr'], $source['categories'][0]['examples']);
        $this->assertSame(4, $source['text_changed']['count']);
        $this->assertSame(['1/fr', '2/fr'], $source['text_changed']['examples']);
    }

    public function testKeepsOneExamplePerRow(): void
    {
        $report = $this->report();
        $report->add($this->roundTrip('1', ['style:color']));
        $report->add($this->roundTrip('1', ['style:color'], locale: 'en'));
        $report->add($this->roundTrip('2', ['style:color']));

        $color = $report->toArray()['sources']['text.text']['categories'][0];

        $this->assertSame(3, $color['values'], 'Both locales of the content 1 count');
        $this->assertSame(['1/fr', '2/fr'], $color['examples'], 'But there is one example by row');
    }

    public function testErrorsAreCountedApartAndAreNotLosses(): void
    {
        $report = $this->report();
        $report->add($this->roundTrip('1', [], false, 'boom'));

        $source = $report->toArray()['sources']['text.text'];

        $this->assertSame(1, $source['errors']);
        $this->assertSame(0, $source['with_loss']);
        $this->assertSame(['1/fr : boom'], $source['error_examples']);
    }

    public function testUnknownSignalsAreReportedAsUnclassified(): void
    {
        $report = $this->report();
        $report->add($this->roundTrip('1', ['attr:li@value']));

        $category = $report->toArray()['sources']['text.text']['categories'][0];

        $this->assertSame('unclassified', $category['key']);
        $this->assertSame('unclassified', $category['status']);
    }

    /**
     * @param list<string> $signals
     */
    private function roundTrip(
        string $id,
        array $signals = [],
        bool $textChanged = false,
        ?string $error = null,
        string $locale = 'fr',
    ): RoundTripResult {
        return new RoundTripResult(
            new AuditedContent('text.text', 'content', $id, null, $locale, '<p>x</p>'),
            null === $error ? '<p>x</p>' : null,
            $textChanged,
            array_map(static fn(string $signal): LostSignal => new LostSignal($signal, 1, 0), $signals),
            $error,
        );
    }

    private function report(int $maxExamples = 3): AuditReport
    {
        return new AuditReport(new LossClassifier([new CkeditorLossRule()]), $maxExamples);
    }
}
