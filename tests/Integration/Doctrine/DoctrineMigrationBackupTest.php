<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Doctrine\Backup\DoctrineMigrationBackup;
use Umanit\TiptapBundle\Doctrine\Entity\BackupRecord;
use Umanit\TiptapBundle\Tests\Fixtures\Doctrine\SqliteDatabase;

#[CoversClass(DoctrineMigrationBackup::class)]
#[CoversClass(BackupRecord::class)]
final class DoctrineMigrationBackupTest extends TestCase
{
    public function testItIsNotReadyUntilTheTableIsCreated(): void
    {
        $this->assertFalse($this->backup(false)->isReady());
        $this->assertTrue($this->backup(true)->isReady());
    }

    public function testContentsAreSavedThenRestoredOnce(): void
    {
        $backup = $this->backup(true);
        $backup->record('m1', 'article.body', '1', '', '<p><b>a</b></p>', '<p><strong>a</strong></p>');
        $backup->record('m1', 'article.body', '2', 'fr', '<b>b</b>', '<strong>b</strong>');
        $backup->record('m2', 'article.body', '3', '', 'c', 'd');

        $entries = $backup->findToRestore('m1');

        $this->assertCount(2, $entries);
        $this->assertSame(
            ['article.body', '1', '', '<p><b>a</b></p>', '<p><strong>a</strong></p>'],
            [$entries[0]->source, $entries[0]->id, $entries[0]->path, $entries[0]->before, $entries[0]->after],
        );

        $backup->markRestored($entries[0]);

        $this->assertCount(1, $backup->findToRestore('m1'));
    }

    public function testAContentWhichHasNotBeenRewrittenCanBeForgotten(): void
    {
        $backup = $this->backup(true);
        $backup->record('m1', 'article.body', '1', '', 'a', 'b');
        $backup->record('m1', 'article.body', '2', '', 'c', 'd');

        $backup->forget('m1', 'article.body', '1', '');

        $entries = $backup->findToRestore('m1');
        $this->assertCount(1, $entries);
        $this->assertSame('2', $entries[0]->id);
    }

    public function testHistoryCountsTheContentsOfEachMigration(): void
    {
        $backup = $this->backup(true);
        $backup->record('m1', 's', '1', '', 'a', 'b');
        $backup->record('m1', 's', '2', '', 'c', 'd');
        $backup->markRestored($backup->findToRestore('m1')[0]);
        $backup->record('m2', 's', '3', '', 'e', 'f');

        $history = $backup->getHistory();

        $this->assertSame(['m1', 2, 1], array_values(\array_slice($history[0], 0, 3)));
        $this->assertSame(['m2', 1, 0], array_values(\array_slice($history[1], 0, 3)));
    }

    public function testTheRecordOfTheMappingReadsBack(): void
    {
        $record = new BackupRecord('m1', 'article.body', '1', 'fr', 'a', 'b');
        $record->markRolledBack();

        $this->assertSame(32, \strlen($record->getId()));
        $this->assertSame(
            ['m1', 'article.body', '1', 'fr', 'a', 'b'],
            [
                $record->getMigrationId(),
                $record->getSource(),
                $record->getEntityId(),
                $record->getPath(),
                $record->getHtmlBefore(),
                $record->getHtmlAfter(),
            ],
        );
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $record->getMigratedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->getRolledBackAt());
    }

    private function backup(bool $withTable): DoctrineMigrationBackup
    {
        return new DoctrineMigrationBackup(
            SqliteDatabase::registryOf(
                SqliteDatabase::create($withTable),
                $this->createStub(ManagerRegistry::class),
            ),
        );
    }
}
