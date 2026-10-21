<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Cleaning\ContentCleaner;
use Umanit\TiptapBundle\Cleaning\HtmlCleaner;
use Umanit\TiptapBundle\Cleaning\Rule\NbspRunsRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordLanguageRule;
use Umanit\TiptapBundle\Command\CleanCommand;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryMigrationBackup;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryWritableSource;

#[CoversClass(CleanCommand::class)]
final class CleanCommandTest extends TestCase
{
    private InMemoryWritableSource $source;

    public function testItSimulatesByDefault(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup());

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Simulation: nothing has been written', $tester->getDisplay());
        $this->assertStringContainsString('word-language', $tester->getDisplay());
        $this->assertSame('<p><span lang="fr">a</span></p>', $this->source->rows['1']['']);
    }

    public function testForceWritesAndGivesTheIdentifierToUndoIt(): void
    {
        $backup = new InMemoryMigrationBackup();
        $tester = $this->tester($backup);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));

        $this->assertSame('<p><span>a</span></p>', $this->source->rows['1']['']);
        $this->assertMatchesRegularExpression(
            '/--rollback=clean-\d{14}-[0-9a-f]{6}/',
            $tester->getDisplay(),
        );
        $this->assertCount(1, $backup->records);
    }

    public function testTheRulesAreListed(): void
    {
        $tester = $this->tester(null);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--list-rules' => true]));
        $this->assertStringContainsString('word-language', $tester->getDisplay());
        $this->assertStringContainsString('nbsp-runs', $tester->getDisplay());
    }

    public function testARuleCanBeChosenOrSkipped(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup());

        $tester->execute(['--force' => true, '--skip-rule' => ['word-language']]);
        $this->assertSame('<p><span lang="fr">a</span></p>', $this->source->rows['1']['']);

        $tester->execute(['--force' => true, '--rule' => ['word-language']]);
        $this->assertSame('<p><span>a</span></p>', $this->source->rows['1']['']);
    }

    public function testDetailsShowTheDifference(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup());

        $tester->execute(['--details' => true]);

        $this->assertStringContainsString('word-language ×1', $tester->getDisplay());
        $this->assertStringContainsString('<span lang="fr">', $tester->getDisplay());
    }

    public function testWritingNeedsABackup(): void
    {
        $withoutBackup = $this->tester(null);
        $this->assertSame(Command::FAILURE, $withoutBackup->execute(['--force' => true]));
        $this->assertStringContainsString('No backup is configured', $withoutBackup->getDisplay());

        $notReady = $this->tester(new InMemoryMigrationBackup(false));
        $this->assertSame(Command::FAILURE, $notReady->execute(['--force' => true]));
        $this->assertStringContainsString('table does not exist yet', $notReady->getDisplay());

        // A simulation needs none
        $this->assertSame(Command::SUCCESS, $this->tester(null)->execute([]));
    }

    public function testInvalidOptionsAreRejected(): void
    {
        $tester = $this->tester(new InMemoryMigrationBackup());

        $this->assertSame(Command::FAILURE, $tester->execute(['--limit' => 'abc']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--examples' => '0']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--rule' => ['nope']]));
        $this->assertStringContainsString('Unknown rule(s): nope', $tester->getDisplay());
        $this->assertSame(Command::FAILURE, $tester->execute(['--source' => ['nope.nope']]));
        $this->assertStringContainsString('Unknown content source', $tester->getDisplay());
        $this->assertSame(Command::FAILURE, $tester->execute(['--group' => 'unknown']));
        $this->assertStringContainsString('No content source matches', $tester->getDisplay());
    }

    private function tester(?MigrationBackupInterface $backup): CommandTester
    {
        $this->source = new InMemoryWritableSource('article.body', ['1' => ['' => '<p><span lang="fr">a</span></p>']]);
        $registry = new ContentSourceRegistry([new FakeContentSourceProvider([$this->source])]);
        $cleaner = new HtmlCleaner([new WordLanguageRule(), new NbspRunsRule()]);

        return new CommandTester(
            new CleanCommand(
                $registry,
                new ContentCleaner($cleaner, $registry, $backup),
                $cleaner,
                $backup,
            ),
        );
    }
}
