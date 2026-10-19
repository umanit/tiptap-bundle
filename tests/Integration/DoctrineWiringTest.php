<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Umanit\TiptapBundle\Tests\Fixtures\DoctrineTestKernel;
use Umanit\TiptapBundle\Tests\Fixtures\TestKernel;

/**
 * The bundle in an application which uses Doctrine: the columns it declares are audited and migrated, and the table of
 * the backup is mapped. Without Doctrine, the command is there but refuses to write.
 */
#[CoversNothing]
final class DoctrineWiringTest extends KernelTestCase
{
    /** @var class-string<KernelInterface> */
    private static string $kernelClass = DoctrineTestKernel::class;

    protected function tearDown(): void
    {
        self::$kernelClass = DoctrineTestKernel::class;

        parent::tearDown();
    }

    protected static function getKernelClass(): string
    {
        return self::$kernelClass;
    }

    public function testTheColumnsOfTheApplicationAreListedAsSources(): void
    {
        $tester = new CommandTester($this->application()->find('umanit:tiptap:audit'));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--list' => true]));
        $this->assertStringContainsString('article.body', $tester->getDisplay());
    }

    public function testTheBackupTableIsMappedAndTheMigrationNeedsIt(): void
    {
        $entityManager = $this->entityManager();

        $sql = implode("\n", new SchemaTool($entityManager)->getCreateSchemaSql($this->metadataOf($entityManager)));
        $this->assertStringContainsString('CREATE TABLE umanit_tiptap_migration_backup', $sql);

        $tester = new CommandTester($this->application()->find('umanit:tiptap:migrate'));

        // The table has not been created by a migration yet
        $this->assertSame(Command::FAILURE, $tester->execute(['--force' => true]));
        $this->assertStringContainsString('table does not exist yet', $tester->getDisplay());
    }

    public function testWithTheTableTheHistoryIsAvailable(): void
    {
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($this->metadataOf($entityManager));

        $tester = new CommandTester($this->application()->find('umanit:tiptap:migrate'));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--history' => true]));
    }

    public function testAColumnIsMigratedThenRestoredWithTheRealEditor(): void
    {
        if (!is_dir(__DIR__ . '/../../assets/node_modules/jsdom')) {
            self::markTestSkipped('The JavaScript dependencies are not installed.');
        }

        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($this->metadataOf($entityManager));
        $connection = $entityManager->getConnection();
        $connection->executeStatement('CREATE TABLE article (id INTEGER PRIMARY KEY, title TEXT, body TEXT)');
        $connection->insert('article', ['id' => 1, 'title' => 'one', 'body' => '<p><b>Bold</b> text</p>']);
        $connection->insert(
            'article',
            ['id' => 2, 'title' => 'two', 'body' => '<p style="margin-left:50px">Indented</p>'],
        );

        $tester = new CommandTester($this->application()->find('umanit:tiptap:migrate'));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--force' => true, '--source' => ['article.body']]));
        $this->assertSame(
            '<p><strong>Bold</strong> text</p>',
            $connection->fetchOne('SELECT body FROM article WHERE id = 1'),
        );
        // Its indentation would be lost: the content is left alone
        $this->assertSame(
            '<p style="margin-left:50px">Indented</p>',
            $connection->fetchOne('SELECT body FROM article WHERE id = 2'),
        );
        $this->assertStringContainsString('blocked by "indent": 1', $tester->getDisplay());

        preg_match('/--rollback=(\S+)/', $tester->getDisplay(), $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertSame(Command::SUCCESS, $tester->execute(['--rollback' => $matches[1], '--force' => true]));
        $this->assertSame('<p><b>Bold</b> text</p>', $connection->fetchOne('SELECT body FROM article WHERE id = 1'));
    }

    public function testWithoutDoctrineTheCommandRefusesToWrite(): void
    {
        self::$kernelClass = TestKernel::class;
        self::ensureKernelShutdown();

        $tester = new CommandTester($this->application()->find('umanit:tiptap:migrate'));

        $this->assertSame(Command::FAILURE, $tester->execute(['--force' => true]));
        $this->assertStringContainsString('No backup is configured', $tester->getDisplay());
    }

    private function application(): Application
    {
        // The database lives in the memory of the kernel: the one of the test is kept
        return new Application(self::$booted && null !== self::$kernel ? self::$kernel : self::bootKernel());
    }

    private function entityManager(): EntityManagerInterface
    {
        $this->application();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    /**
     * @return list<ClassMetadata<object>>
     */
    private function metadataOf(EntityManagerInterface $entityManager): array
    {
        return $entityManager->getMetadataFactory()->getAllMetadata();
    }
}
