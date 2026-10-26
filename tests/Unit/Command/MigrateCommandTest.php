<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Command\MigrateCommand;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Migration\ContentMigrator;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryMigrationBackup;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryWritableSource;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\ScriptedRoundTripRunner;

#[CoversClass(MigrateCommand::class)]
final class MigrateCommandTest extends TestCase
{
    private InMemoryWritableSource $source;
    private ScriptedRoundTripRunner $runner;

    public function testItSimulatesByDefault(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup());

        $this->assertSame(Command::SUCCESS, $tester->execute(['--source' => ['article.body']]));
        $this->assertStringContainsString('Simulation: nothing has been written', $tester->getDisplay());
        $this->assertStringContainsString('migrated', $tester->getDisplay());
        $this->assertSame('<p><b>x</b></p>', $this->source->rows['1']['']);
    }

    public function testForceWritesAndGivesTheIdentifierToUndoIt(): void
    {
        $backup = new InMemoryMigrationBackup();
        $tester = $this->tester($backup);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--force' => true, '--legacy-styles' => 'palette.json']));

        $this->assertSame('<p><strong>x</strong></p>', $this->source->rows['1']['']);
        $this->assertSame('palette.json', $this->runner->legacyStylesFile);
        $this->assertMatchesRegularExpression('/--rollback=\d{14}-[0-9a-f]{6}/', $tester->getDisplay());
        $this->assertCount(1, $backup->records);
    }

    public function testRollbackRestoresTheContents(): void
    {
        $backup = new InMemoryMigrationBackup();
        $tester = $this->tester($backup);
        $tester->execute(['--force' => true]);
        $migration = array_values($backup->records)[0]['migration'];

        $this->assertSame(Command::SUCCESS, $tester->execute(['--rollback' => $migration, '--force' => true]));

        $this->assertSame('<p><b>x</b></p>', $this->source->rows['1']['']);
        $this->assertStringContainsString('Rollback of the migration ' . $migration, $tester->getDisplay());
    }

    public function testHistoryListsTheMigrations(): void
    {
        $backup = new InMemoryMigrationBackup();
        $tester = $this->tester($backup);
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--history' => true]));
        $this->assertStringContainsString('Contents saved', $tester->getDisplay());
    }

    public function testPurgingAMigrationIsASimulationUntilItIsForced(): void
    {
        $backup = new InMemoryMigrationBackup();
        $tester = $this->tester($backup);
        $tester->execute(['--force' => true]);
        $migration = array_values($backup->records)[0]['migration'];

        $this->assertSame(Command::SUCCESS, $tester->execute(['--purge' => $migration]));
        $this->assertCount(1, $backup->records);
        $this->assertStringContainsString('Simulation: nothing has been deleted', $tester->getDisplay());
        $this->assertStringContainsString($migration, $tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $tester->execute(['--purge' => $migration, '--force' => true]));
        $this->assertSame([], $backup->records);
        $this->assertStringContainsString('1 content(s) of 1 migration(s) purged', $tester->getDisplay());
    }

    public function testOnlyTheMigrationsOlderThanTheGivenDaysArePurged(): void
    {
        $backup = new InMemoryMigrationBackup();
        $backup->record('old', 's', '1', '', 'a', 'b');
        $backup->record('recent', 's', '2', '', 'c', 'd');
        $backup->dates = [
            'old'    => new \DateTimeImmutable('-40 days')->format('Y-m-d H:i:s'),
            'recent' => new \DateTimeImmutable('-2 days')->format('Y-m-d H:i:s'),
        ];
        $tester = $this->tester($backup);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--purge-older-than' => '30', '--force' => true]));

        $this->assertSame(['recent|s|2|'], array_keys($backup->records));
    }

    public function testPurgeRefusesWhatItCannotUnderstand(): void
    {
        $backup = new InMemoryMigrationBackup();
        $backup->record('m1', 's', '1', '', 'a', 'b');
        $tester = $this->tester($backup);

        $this->assertSame(Command::FAILURE, $tester->execute(['--purge' => 'unknown', '--force' => true]));
        $this->assertStringContainsString('No saved migration has this identifier', $tester->getDisplay());
        $this->assertSame(Command::FAILURE, $tester->execute(['--purge' => 'm1', '--purge-older-than' => '5']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--purge-older-than' => '0']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--purge' => 'm1', '--rollback' => 'm1']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--purge' => 'm1', '--history' => true]));
        $this->assertCount(1, $backup->records);
        $this->assertSame(Command::FAILURE, $this->tester(null)->execute(['--purge' => 'm1']));
    }

    public function testAnAcceptedLossIsExplainedWhenItBlocks(): void
    {
        $html = '<p style="margin-left:40px">x</p>';
        $this->source = new InMemoryWritableSource('article.body', ['1' => ['' => $html]]);
        $this->runner = new ScriptedRoundTripRunner([$html => ['<p>x</p>', ['style:margin-left'], false]]);
        $tester = $this->tester(new InMemoryMigrationBackup(), false);

        $tester->execute(['--force' => true]);

        $this->assertStringContainsString('blocked by "indent": 1', $tester->getDisplay());
        $this->assertSame('<p style="margin-left:40px">x</p>', $this->source->rows['1']['']);

        $tester->execute(['--force' => true, '--accept' => ['indent']]);

        $this->assertSame('<p>x</p>', $this->source->rows['1']['']);
    }

    public function testASourceWhichCannotBeWrittenIsWarnedAbout(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup(), true, [new FakeContentSource('readonly.body')]);

        $tester->execute([]);

        $this->assertStringContainsString('cannot be written', $tester->getDisplay());
        $this->assertStringContainsString('readonly.body', $tester->getDisplay());
    }

    public function testWritingNeedsABackup(): void
    {
        $withoutBackup = $this->tester(null);
        $this->assertSame(Command::FAILURE, $withoutBackup->execute(['--force' => true]));
        $this->assertStringContainsString('No backup is configured', $withoutBackup->getDisplay());

        $notReady = $this->tester(new InMemoryMigrationBackup(false));
        $this->assertSame(Command::FAILURE, $notReady->execute(['--force' => true]));
        $this->assertStringContainsString('table does not exist yet', $notReady->getDisplay());
        $this->assertSame('<p><b>x</b></p>', $this->source->rows['1']['']);

        // A simulation needs none
        $this->assertSame(Command::SUCCESS, $this->tester(null)->execute([]));
    }

    public function testInvalidOptionsAreRejected(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup());

        $this->assertSame(Command::FAILURE, $tester->execute(['--workers' => '0']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--limit' => 'abc']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--source' => ['nope.nope']]));
        $this->assertStringContainsString('Unknown content source', $tester->getDisplay());
        $this->assertSame(Command::FAILURE, $tester->execute(['--group' => 'unknown']));
        $this->assertStringContainsString('No content source matches', $tester->getDisplay());
    }

    /**
     * @param list<ContentSourceInterface> $others
     */
    private function tester(
        ?MigrationBackupInterface $backup,
        bool $defaultSource = true,
        array $others = [],
    ): CommandTester {
        if ($defaultSource) {
            $this->source = new InMemoryWritableSource('article.body', ['1' => ['' => '<p><b>x</b></p>']]);
            $this->runner = new ScriptedRoundTripRunner([
                '<p><b>x</b></p>' => ['<p><strong>x</strong></p>', [], false],
            ]);
        }

        $registry = new ContentSourceRegistry([new FakeContentSourceProvider([$this->source, ...$others])]);

        return new CommandTester(
            new MigrateCommand(
                $registry,
                new ContentMigrator($this->runner, new LossClassifier([new CkeditorLossRule()]), $registry, $backup),
                $backup,
            ),
        );
    }
}
