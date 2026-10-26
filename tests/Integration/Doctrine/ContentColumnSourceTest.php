<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;
use Umanit\TiptapBundle\Doctrine\ContentColumn;
use Umanit\TiptapBundle\Doctrine\ContentColumnProviderInterface;
use Umanit\TiptapBundle\Doctrine\ContentColumnSource;
use Umanit\TiptapBundle\Doctrine\ContentColumnSourceProvider;
use Umanit\TiptapBundle\Doctrine\Storage\ColumnStorageRegistry;
use Umanit\TiptapBundle\Doctrine\Storage\JsonColumnStorage;
use Umanit\TiptapBundle\Doctrine\Storage\ScalarColumnStorage;
use Umanit\TiptapBundle\Tests\Fixtures\Doctrine\SqliteDatabase;

#[CoversClass(ContentColumnSource::class)]
#[CoversClass(ContentColumnSourceProvider::class)]
final class ContentColumnSourceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = SqliteDatabase::create();
        $this->connection->executeStatement('CREATE TABLE parameter (id INTEGER PRIMARY KEY, name TEXT, value TEXT)');
        $this->connection->executeStatement('CREATE TABLE block (id INTEGER PRIMARY KEY, content TEXT)');
    }

    public function testAScalarColumnYieldsTheContentsWhichLookLikeHtml(): void
    {
        $this->connection->insert('parameter', ['id' => 1, 'name' => 'login.title', 'value' => '<p>x</p>']);
        $this->connection->insert('parameter', ['id' => 2, 'name' => 'login.plain', 'value' => 'plain text']);
        $this->connection->insert('parameter', ['id' => 3, 'name' => 'login.empty', 'value' => null]);

        $source = $this->source(new ContentColumn('parameter', 'value', labelColumn: 'name'));
        $contents = iterator_to_array($source->read(), false);

        $this->assertCount(1, $contents);
        $this->assertSame('1 (login.title)', $contents[0]->describe());
        $this->assertSame('parameter.value', $contents[0]->source);
        $this->assertSame('<p>x</p>', $contents[0]->html);
    }

    public function testAJsonColumnIsWalkedAtAnyDepth(): void
    {
        $json = json_encode(['text' => ['fr' => '<p>x</p>'], 'items' => [['body' => '<ul><li>y</li></ul>']]]);
        $this->connection->insert('block', ['id' => 3, 'content' => $json]);

        $source = $this->source(new ContentColumn('block', 'content', ContentColumn::STORAGE_JSON));

        $this->assertSame(
            ['text.fr', 'items.0.body'],
            array_map(static fn($content): string => $content->path, iterator_to_array($source->read(), false)),
        );
    }

    public function testAnInvalidDocumentIsReported(): void
    {
        $this->connection->insert('block', ['id' => 1, 'content' => '{nope']);

        $this->expectException(\JsonException::class);

        iterator_to_array($this->source(new ContentColumn('block', 'content', ContentColumn::STORAGE_JSON))->read());
    }

    public function testTheSourceRewritesWhatItReads(): void
    {
        $this->connection->insert('parameter', ['id' => 1, 'name' => 'a', 'value' => '<p><b>x</b></p>']);
        $source = $this->source(new ContentColumn('parameter', 'value'));

        $this->assertSame([''], $source->replace('1', ['' => ['<p><b>x</b></p>', '<p><strong>x</strong></p>']]));
        $this->assertSame('<p><strong>x</strong></p>', $this->connection->fetchOne('SELECT value FROM parameter'));
        $this->assertSame('content', $source->getGroup());
        $this->assertSame('parameter.value', $source->getColumn()->getName());
    }

    public function testALaterProviderReplacesAnEarlierDeclaration(): void
    {
        $bundle = $this->columns(new ContentColumn('parameter', 'value'), new ContentColumn('block', 'content'));
        $project = $this->columns(new ContentColumn('parameter', 'value', group: 'project'));

        $provider = new ContentColumnSourceProvider(
            [$bundle, $project],
            new ColumnStorageRegistry([new ScalarColumnStorage(), new JsonColumnStorage()]),
            SqliteDatabase::registryOf($this->connection, $this->createStub(ManagerRegistry::class)),
            new HtmlContentExtractor(),
        );
        $groups = [];

        foreach ($provider->getSources() as $source) {
            $groups[$source->getName()] = $source->getGroup();
        }

        $this->assertSame(['parameter.value' => 'project', 'block.content' => 'content'], $groups);
    }

    public function testTheRowsAreReadPageByPageInTheOrderOfTheirIdentifier(): void
    {
        $this->connection->beginTransaction();

        for ($id = 1; $id <= 1203; ++$id) {
            $this->connection->insert('parameter', ['id' => $id, 'name' => 'n' . $id, 'value' => '<p>' . $id . '</p>']);
        }

        $this->connection->commit();

        $ids = array_map(
            static fn($content): string => $content->id,
            iterator_to_array($this->source(new ContentColumn('parameter', 'value'))->read(), false),
        );

        $this->assertSame(array_map(strval(...), range(1, 1203)), $ids);
    }

    public function testRowsWrittenWhileReadingAreNeitherSkippedNorReadTwice(): void
    {
        for ($id = 1; $id <= 700; ++$id) {
            $this->connection->insert('parameter', ['id' => $id, 'name' => 'n', 'value' => '<p><b>x</b></p>']);
        }

        $source = $this->source(new ContentColumn('parameter', 'value'));
        $seen = [];

        foreach ($source->read() as $content) {
            $seen[] = $content->id;
            $source->replace($content->id, ['' => [$content->html, '<p><strong>x</strong></p>']]);
        }

        $this->assertSame(array_map(strval(...), range(1, 700)), $seen);
        $this->assertSame(
            0,
            $this->connection->fetchOne('SELECT COUNT(*) FROM parameter WHERE value LIKE \'%<b>%\''),
        );
    }

    public function testIdentifiersWhichAreNotIntegersArePaged(): void
    {
        $this->connection->executeStatement('CREATE TABLE token (id TEXT PRIMARY KEY, value TEXT)');

        for ($i = 0; $i < 1100; ++$i) {
            $this->connection->insert('token', ['id' => \sprintf('uuid-%05d', $i), 'value' => '<p>x</p>']);
        }

        $contents = iterator_to_array($this->source(new ContentColumn('token', 'value'))->read(), false);

        $this->assertCount(1100, $contents);
        $this->assertSame('uuid-01099', $contents[1099]->id);
    }

    public function testTheColumnIsReadOnTheConnectionItNames(): void
    {
        $this->connection->insert('parameter', ['id' => 1, 'name' => 'a', 'value' => '<p>x</p>']);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->atLeastOnce())
            ->method('getConnection')
            ->with('reporting')
            ->willReturn($this->connection)
        ;

        $column = new ContentColumn('parameter', 'value', connection: 'reporting');
        $source = new ContentColumnSource(
            $column,
            new ScalarColumnStorage(),
            $registry,
            new HtmlContentExtractor(),
        );

        $this->assertCount(1, iterator_to_array($source->read(), false));
        $this->assertSame('reporting:parameter.value', $source->getName());
    }

    private function source(ContentColumn $column): ContentColumnSource
    {
        $storages = new ColumnStorageRegistry([new ScalarColumnStorage(), new JsonColumnStorage()]);

        return new ContentColumnSource(
            $column,
            $storages->get($column->storage),
            SqliteDatabase::registryOf($this->connection, $this->createStub(ManagerRegistry::class)),
            new HtmlContentExtractor(),
        );
    }

    private function columns(ContentColumn ...$columns): ContentColumnProviderInterface
    {
        return new readonly class (array_values($columns)) implements ContentColumnProviderInterface {
            /**
             * @param list<ContentColumn> $columns
             */
            public function __construct(private array $columns)
            {
            }

            public function getColumns(): iterable
            {
                return $this->columns;
            }
        };
    }
}
