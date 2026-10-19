<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripRunnerInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Migration\ContentMigrator;
use Umanit\TiptapBundle\Migration\MigrationOptions;
use Umanit\TiptapBundle\Migration\MigrationOutcome;
use Umanit\TiptapBundle\Migration\MigrationReport;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryMigrationBackup;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryWritableSource;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\ScriptedRoundTripRunner;

#[CoversClass(ContentMigrator::class)]
#[CoversClass(MigrationReport::class)]
#[CoversClass(MigrationOptions::class)]
#[CoversClass(MigrationOutcome::class)]
final class ContentMigratorTest extends TestCase
{
    private InMemoryWritableSource $source;
    private InMemoryMigrationBackup $backup;

    public function testASimulationWritesNothing(): void
    {
        $report = $this->migrate(
            ['<p><b>x</b></p>' => ['<p><strong>x</strong></p>', [], false]],
            new MigrationOptions(),
        );

        $this->assertSame(1, $report->count(MigrationOutcome::Migrated));
        $this->assertNull($report->getMigrationId());
        $this->assertSame('<p><b>x</b></p>', $this->source->rows['1']['']);
        $this->assertSame([], $this->backup->records);
    }

    public function testAContentIsSavedThenRewritten(): void
    {
        $report = $this->migrate(
            ['<p><b>x</b></p>' => ['<p><strong>x</strong></p>', [], false]],
            new MigrationOptions(apply: true),
        );

        $this->assertSame(1, $report->count(MigrationOutcome::Migrated, 'article.body'));
        $this->assertSame('m1', $report->getMigrationId());
        $this->assertSame('<p><strong>x</strong></p>', $this->source->rows['1']['']);

        $entry = array_values($this->backup->records)[0]['entry'];
        $this->assertSame(['article.body', '1', '', '<p><b>x</b></p>', '<p><strong>x</strong></p>'], [
            $entry->source,
            $entry->id,
            $entry->path,
            $entry->before,
            $entry->after,
        ]);
    }

    public function testAContentWhichWouldLoseItsFormattingIsLeftAsItIs(): void
    {
        $html = '<p style="margin-left:40px">x</p>';

        $report = $this->migrate(
            [$html => ['<p>x</p>', ['style:margin-left'], false]],
            new MigrationOptions(apply: true),
        );

        $this->assertSame(1, $report->count(MigrationOutcome::Blocked));
        $this->assertSame(['indent' => 1], $report->getBlockedCategories('article.body'));
        $this->assertSame($html, $this->source->rows['1']['']);
    }

    public function testALossCanBeAcceptedByCategoryOrByStatus(): void
    {
        $html = '<p style="margin-left:40px">x</p>';
        $results = [$html => ['<p>x</p>', ['style:margin-left'], false]];

        $this->assertSame(
            1,
            $this->migrate($results, new MigrationOptions(accepted: ['indent']))->count(MigrationOutcome::Migrated),
        );
        $this->assertSame(
            1,
            $this->migrate($results, new MigrationOptions(accepted: ['decision']))->count(MigrationOutcome::Migrated),
        );
    }

    public function testALossWithoutVisibleEffectDoesNotBlock(): void
    {
        $report = $this->migrate(
            ['<p lang="fr">x</p>' => ['<p>x</p>', ['attr:p@lang'], false]],
            new MigrationOptions(apply: true),
        );

        $this->assertSame(1, $report->count(MigrationOutcome::Migrated));
    }

    public function testATextWhichChangesIsNeverMigrated(): void
    {
        $report = $this->migrate(
            ['<p>x</p><script>y</script>' => ['<p>x</p>', [], true]],
            new MigrationOptions(accepted: ['decision', 'unclassified'], apply: true),
        );

        $this->assertSame(1, $report->count(MigrationOutcome::TextChanged));
        $this->assertSame('<p>x</p><script>y</script>', $this->source->rows['1']['']);
    }

    public function testAnUnchangedContentIsNotRewritten(): void
    {
        $report = $this->migrate(['<p>x</p>' => ['<p>x</p>', [], false]], new MigrationOptions(apply: true));

        $this->assertSame(1, $report->count(MigrationOutcome::Unchanged));
        $this->assertSame([], $this->backup->records);
    }

    public function testAContentEditedMeanwhileIsALeftAloneAndItsBackupForgotten(): void
    {
        $this->source = new InMemoryWritableSource('article.body', ['1' => ['' => '<p><b>x</b></p>']]);
        // The source is edited after the contents have been read, before they are written
        $editing = new readonly class ($this->source) implements RoundTripRunnerInterface {
            public function __construct(private InMemoryWritableSource $source)
            {
            }

            public function run(iterable $contents, int $workers = 4, ?string $legacyStylesFile = null): \Generator
            {
                foreach ($contents as $content) {
                    $this->source->rows['1'][''] = '<p>edited</p>';

                    yield new RoundTripResult($content, '<p><strong>x</strong></p>', false, []);
                }
            }
        };
        $this->backup = new InMemoryMigrationBackup();

        $report = $this->migrator($editing)->migrate([$this->source], 'm1', new MigrationOptions(apply: true));

        $this->assertSame(1, $report->count(MigrationOutcome::Conflict));
        $this->assertSame('<p>edited</p>', $this->source->rows['1']['']);
        $this->assertSame([], $this->backup->records);
    }

    public function testASourceWhichCannotBeWrittenIsReportedAndLeftAlone(): void
    {
        $readOnly = new FakeContentSource('readonly.body');
        $this->source = new InMemoryWritableSource('article.body', []);
        $runner = new ScriptedRoundTripRunner([]);
        $this->backup = new InMemoryMigrationBackup();

        $report = $this->migrator($runner)->migrate([$readOnly], 'm1', new MigrationOptions(apply: true));

        $this->assertSame(['readonly.body'], $report->getReadOnlySources());
        $this->assertSame([], $report->getSources());
    }

    public function testNothingIsWrittenWithoutAPlaceToSaveTheContents(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nowhere to save');

        $source = new InMemoryWritableSource('article.body', ['1' => ['' => '<p>x</p>']]);

        new ContentMigrator(
            new ScriptedRoundTripRunner([]),
            new LossClassifier([new CkeditorLossRule()]),
            new ContentSourceRegistry([new FakeContentSourceProvider([$source])]),
        )->migrate([$source], 'm1', new MigrationOptions(apply: true));
    }

    public function testTheOptionsAreGivenToTheRunner(): void
    {
        $runner = new ScriptedRoundTripRunner(['<p>x</p>' => ['<p>x</p>', [], false]]);
        $this->source = new InMemoryWritableSource('article.body', ['1' => ['' => '<p>x</p>']]);
        $this->backup = new InMemoryMigrationBackup();

        $options = new MigrationOptions(legacyStylesFile: 'palette.json');

        $this->migrator($runner)->migrate([$this->source], 'm1', $options);

        $this->assertSame('palette.json', $runner->legacyStylesFile);
    }

    public function testRollbackRestoresTheOriginalContentsExceptTheOnesEditedSince(): void
    {
        $this->source = new InMemoryWritableSource('article.body', [
            '1' => ['' => '<p><b>one</b></p>'],
            '2' => ['' => '<p><b>two</b></p>'],
        ]);
        $this->backup = new InMemoryMigrationBackup();
        $migrator = $this->migrator(new ScriptedRoundTripRunner([
            '<p><b>one</b></p>' => ['<p><strong>one</strong></p>', [], false],
            '<p><b>two</b></p>' => ['<p><strong>two</strong></p>', [], false],
        ]));
        $migrator->migrate([$this->source], 'm1', new MigrationOptions(apply: true));

        $this->source->rows['2'][''] = '<p>edited since</p>';

        $simulation = $migrator->rollback('m1');
        $this->assertSame(2, $simulation->count(MigrationOutcome::Restored));
        $this->assertSame('<p><strong>one</strong></p>', $this->contentOf('1'));

        $report = $migrator->rollback('m1', true);

        $this->assertSame(1, $report->count(MigrationOutcome::Restored));
        $this->assertSame(1, $report->count(MigrationOutcome::Conflict));
        $this->assertSame('<p><b>one</b></p>', $this->contentOf('1'));
        $this->assertSame('<p>edited since</p>', $this->contentOf('2'));
        $this->assertSame(0, $migrator->rollback('m1', true)->count(MigrationOutcome::Restored));
    }

    public function testRollbackNeedsABackup(): void
    {
        $this->expectException(\RuntimeException::class);

        new ContentMigrator(
            new ScriptedRoundTripRunner([]),
            new LossClassifier([]),
            new ContentSourceRegistry([]),
            new InMemoryMigrationBackup(false),
        )->rollback('m1');
    }

    public function testTheReportKeepsOnlyTheRequestedNumberOfExamples(): void
    {
        $report = new MigrationReport(2);

        foreach (['a', 'b', 'c'] as $reference) {
            $report->add('s', MigrationOutcome::Blocked, $reference, ['table']);
        }

        $this->assertSame(['a', 'b'], $report->getExamples('s', MigrationOutcome::Blocked));
        $this->assertSame(['table' => 3], $report->getBlockedCategories('s'));
        $this->assertSame([], $report->getExamples('s', MigrationOutcome::Migrated));
        $this->assertNotSame('', MigrationOutcome::Migrated->getLabel());
    }

    private function contentOf(string $id): string
    {
        return $this->source->rows[$id][''] ?? '';
    }

    /**
     * @param array<string, array{string, list<string>, bool}> $results
     */
    private function migrate(array $results, MigrationOptions $options): MigrationReport
    {
        // The only content of the source is the one the runner is scripted for
        $this->source = new InMemoryWritableSource('article.body', ['1' => ['' => (string) array_key_first($results)]]);
        $this->backup = new InMemoryMigrationBackup();

        return $this->migrator(new ScriptedRoundTripRunner($results))->migrate([$this->source], 'm1', $options);
    }

    private function migrator(RoundTripRunnerInterface $runner): ContentMigrator
    {
        return new ContentMigrator(
            $runner,
            new LossClassifier([new CkeditorLossRule()]),
            new ContentSourceRegistry([new FakeContentSourceProvider([$this->source])]),
            $this->backup,
        );
    }
}
