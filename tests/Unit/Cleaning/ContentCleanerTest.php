<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Cleaning;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Audit\Source\WritableContentSourceInterface;
use Umanit\TiptapBundle\Cleaning\CleaningOptions;
use Umanit\TiptapBundle\Cleaning\CleaningOutcome;
use Umanit\TiptapBundle\Cleaning\CleaningReport;
use Umanit\TiptapBundle\Cleaning\ContentCleaner;
use Umanit\TiptapBundle\Cleaning\HtmlCleaner;
use Umanit\TiptapBundle\Cleaning\Rule\WordLanguageRule;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\ContentMigrator;
use Umanit\TiptapBundle\Migration\MigrationOutcome;
use Umanit\TiptapBundle\Migration\MigrationPhase;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryMigrationBackup;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryWritableSource;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\ScriptedRoundTripRunner;

#[CoversClass(ContentCleaner::class)]
#[CoversClass(CleaningReport::class)]
#[CoversClass(CleaningOptions::class)]
final class ContentCleanerTest extends TestCase
{
    public const string DIRTY = '<p><span lang="fr">a</span></p>';
    private const string CLEAN = '<p><span>a</span></p>';

    public function testASimulationWritesNothing(): void
    {
        [$cleaner, $source, $backup] = $this->build();

        $report = $cleaner->clean([$source], 'clean-1', new CleaningOptions());

        $this->assertSame(1, $report->count(CleaningOutcome::Cleaned));
        $this->assertSame(1, $report->count(CleaningOutcome::Unchanged));
        $this->assertSame(['word-language' => ['contents' => 1, 'changes' => 1]], $report->getRules('article.body'));
        $this->assertSame(self::DIRTY, $source->rows['1']['']);
        $this->assertSame([], $backup->records);
        $this->assertNull($report->getCleaningId());
    }

    public function testItWritesAfterHavingSavedTheOriginalContent(): void
    {
        [$cleaner, $source, $backup] = $this->build();

        $report = $cleaner->clean([$source], 'clean-1', new CleaningOptions(apply: true));

        $this->assertSame(self::CLEAN, $source->rows['1']['']);
        $this->assertSame('<p>b</p>', $source->rows['2']['']);
        $this->assertSame('clean-1', $report->getCleaningId());
        $this->assertCount(1, $backup->records);
        $this->assertSame(self::DIRTY, \array_values($backup->records)[0]['entry']->before);
    }

    public function testTheMigrationRollbackPutsTheContentBack(): void
    {
        [$cleaner, $source, $backup] = $this->build();
        $cleaner->clean([$source], 'clean-1', new CleaningOptions(apply: true));
        $registry = new ContentSourceRegistry([new FakeContentSourceProvider([$source])]);
        $migrator = new ContentMigrator(
            new ScriptedRoundTripRunner([]),
            new LossClassifier([new CkeditorLossRule()]),
            $registry,
            $backup,
        );

        $report = $migrator->rollback('clean-1', true);

        $this->assertSame(self::DIRTY, $source->rows['1']['']);
        $this->assertSame(1, $report->count(MigrationOutcome::Restored));
    }

    public function testAContentEditedMeanwhileIsLeftAloneAndNotSaved(): void
    {
        // The content is edited between its reading and its writing: the source refuses to replace it
        $source = new class implements WritableContentSourceInterface {
            public function getName(): string
            {
                return 'article.body';
            }

            public function getGroup(): string
            {
                return ContentSourceRegistry::DEFAULT_GROUP;
            }

            public function read(): \Generator
            {
                yield new AuditedContent(
                    'article.body',
                    ContentSourceRegistry::DEFAULT_GROUP,
                    '1',
                    null,
                    '',
                    ContentCleanerTest::DIRTY,
                );
            }

            public function replace(string $id, array $changes): array
            {
                return [];
            }
        };
        $backup = new InMemoryMigrationBackup();
        $cleaner = $this->cleaner($backup, $source);

        $report = $cleaner->clean([$source], 'clean-1', new CleaningOptions(apply: true));

        $this->assertSame(1, $report->count(CleaningOutcome::Conflict));
        $this->assertSame(0, $report->count(CleaningOutcome::Cleaned));
        $this->assertSame([], $backup->records);
    }

    public function testItNeedsABackupToWrite(): void
    {
        [$cleaner, $source] = $this->build(new InMemoryMigrationBackup(false));

        $this->expectException(\RuntimeException::class);

        $cleaner->clean([$source], 'clean-1', new CleaningOptions(apply: true));
    }

    public function testASourceWhichCannotBeWrittenIsReported(): void
    {
        [$cleaner] = $this->build();

        $report = $cleaner->clean([new FakeContentSource('readonly.body')], 'clean-1');

        $this->assertSame(['readonly.body'], $report->getReadOnlySources());
    }

    public function testTheRulesAndTheLimitAreHonored(): void
    {
        [$cleaner, $source] = $this->build();

        $none = $cleaner->clean([$source], 'clean-1', new CleaningOptions(skip: ['word-language']));
        $limited = $cleaner->clean([$source], 'clean-1', new CleaningOptions(limit: 1));

        $this->assertSame(0, $none->count(CleaningOutcome::Cleaned));
        $this->assertSame(1, $limited->count(CleaningOutcome::Cleaned) + $limited->count(CleaningOutcome::Unchanged));
    }

    public function testDetailsKeepTheExamples(): void
    {
        [$cleaner, $source] = $this->build();

        $report = $cleaner->clean([$source], 'clean-1', new CleaningOptions(maxExamples: 1, details: true));
        $details = $report->getDetails('article.body');

        $this->assertCount(1, $details);
        $this->assertSame('1', $details[0]->reference);
        $this->assertSame(self::DIRTY, $details[0]->before);
        $this->assertSame(self::CLEAN, $details[0]->after);
    }

    public function testItReportsItsProgress(): void
    {
        [$cleaner, $source] = $this->build();
        $phases = [];

        $cleaner->clean(
            [$source],
            'clean-1',
            new CleaningOptions(apply: true),
            static function (MigrationPhase $phase) use (&$phases): void {
                $phases[$phase->name] = true;
            },
        );

        $this->assertSame(['Analysis', 'Writing'], \array_keys($phases));
    }

    /**
     * @return array{ContentCleaner, InMemoryWritableSource, MigrationBackupInterface&InMemoryMigrationBackup}
     */
    private function build(?InMemoryMigrationBackup $backup = null): array
    {
        $source = new InMemoryWritableSource('article.body', ['1' => ['' => self::DIRTY], '2' => ['' => '<p>b</p>']]);
        $backup ??= new InMemoryMigrationBackup();

        return [$this->cleaner($backup, $source), $source, $backup];
    }

    private function cleaner(MigrationBackupInterface $backup, WritableContentSourceInterface $source): ContentCleaner
    {
        return new ContentCleaner(
            new HtmlCleaner([new WordLanguageRule()]),
            new ContentSourceRegistry([new FakeContentSourceProvider([$source])]),
            $backup,
        );
    }
}
