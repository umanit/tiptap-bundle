<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Doctrine\ContentColumn;
use Umanit\TiptapBundle\Doctrine\Storage\ColumnStorageRegistry;
use Umanit\TiptapBundle\Doctrine\Storage\JsonColumnStorage;
use Umanit\TiptapBundle\Doctrine\Storage\ScalarColumnStorage;
use Umanit\TiptapBundle\Tests\Fixtures\Doctrine\SqliteDatabase;

#[CoversClass(ScalarColumnStorage::class)]
#[CoversClass(JsonColumnStorage::class)]
#[CoversClass(ColumnStorageRegistry::class)]
#[CoversClass(ContentColumn::class)]
final class ColumnStorageTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = SqliteDatabase::create();
        $this->connection->executeStatement('CREATE TABLE article (id INTEGER PRIMARY KEY, body TEXT, doc TEXT)');
    }

    public function testScalarIsReplacedOnlyWhenItIsStillTheContentThatWasRead(): void
    {
        $this->connection->insert('article', ['id' => 1, 'body' => '<p>old</p>']);
        $column = new ContentColumn('article', 'body');
        $storage = new ScalarColumnStorage();

        $this->assertSame([], $storage->write($this->connection, $column, '1', ['' => ['<p>other</p>', '<p>new</p>']]));
        $this->assertSame('<p>old</p>', $this->fetch('SELECT body FROM article WHERE id = 1'));

        $this->assertSame([''], $storage->write($this->connection, $column, '1', ['' => ['<p>old</p>', '<p>new</p>']]));
        $this->assertSame('<p>new</p>', $this->fetch('SELECT body FROM article WHERE id = 1'));
    }

    public function testJsonIsReplacedAtAnyDepthAndWrittenBack(): void
    {
        $document = [
            'text'  => ['fr' => '<b>x</b>'],
            'items' => [['body' => '<i>y</i>'], ['body' => '<i>z</i>']],
            'n'     => 3,
        ];
        $this->connection->insert('article', ['id' => 7, 'doc' => json_encode($document)]);
        $column = new ContentColumn('article', 'doc', ContentColumn::STORAGE_JSON);

        $written = new JsonColumnStorage()->write($this->connection, $column, '7', [
            'text.fr'      => ['<b>x</b>', '<strong>x</strong>'],
            'items.1.body' => ['<i>z</i>', '<em>z</em>'],
            'items.0.body' => ['<i>not what was read</i>', '<em>y</em>'],
        ]);

        $this->assertEqualsCanonicalizing(['text.fr', 'items.1.body'], $written);
        $this->assertEquals(
            [
                'text'  => ['fr' => '<strong>x</strong>'],
                'items' => [['body' => '<i>y</i>'], ['body' => '<em>z</em>']],
                'n'     => 3,
            ],
            json_decode($this->fetch('SELECT doc FROM article WHERE id = 7'), true),
        );
    }

    public function testNothingIsWrittenWhenNoPathMatchesOrTheRowIsGone(): void
    {
        $this->connection->insert('article', ['id' => 7, 'doc' => json_encode(['a' => '<p>x</p>'])]);
        $column = new ContentColumn('article', 'doc', ContentColumn::STORAGE_JSON);
        $storage = new JsonColumnStorage();

        $this->assertSame([], $storage->write($this->connection, $column, '7', ['b' => ['<p>x</p>', '<p>y</p>']]));
        $this->assertSame([], $storage->write($this->connection, $column, '8', ['a' => ['<p>x</p>', '<p>y</p>']]));
    }

    public function testJsonDecodesTheDocument(): void
    {
        $this->assertSame(['a' => ['b' => '<p>x</p>']], new JsonColumnStorage()->decode('{"a":{"b":"<p>x<\/p>"}}'));
        $this->assertSame('<p>x</p>', new ScalarColumnStorage()->decode('<p>x</p>'));
    }

    public function testTheRegistryFindsAStorageByName(): void
    {
        $registry = new ColumnStorageRegistry([new ScalarColumnStorage(), new JsonColumnStorage()]);

        $this->assertInstanceOf(JsonColumnStorage::class, $registry->get('json'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Known storages: scalar, json');

        $registry->get('hstore');
    }

    public function testAColumnIsNamedAfterItsTableAndColumn(): void
    {
        $this->assertSame('article.body', new ContentColumn('article', 'body')->getName());
    }

    private function fetch(string $sql): string
    {
        $value = $this->connection->fetchOne($sql);

        return \is_string($value) ? $value : '';
    }
}
