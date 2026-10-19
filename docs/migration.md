# Migrating existing contents

`umanit:tiptap:migrate` rewrites the contents written with another editor (CKEditor…) as the TipTap editor writes them,
so that nothing changes the first time someone opens one of them. Measure first with
[`umanit:tiptap:audit`](migration-audit.md), and build the palette of your colors and sizes with
[`umanit:tiptap:palette`](palette.md).

```bash
bin/console umanit:tiptap:migrate # simulation: nothing is written
bin/console umanit:tiptap:migrate --legacy-styles=var/palette/legacy-styles.json
bin/console umanit:tiptap:migrate --force --legacy-styles=… # writes
bin/console umanit:tiptap:migrate --rollback=20261019101500-a1b2c3 --force
bin/console umanit:tiptap:migrate --history
```

## What is rewritten

Each content goes through the schema of the editor (under Node, like the audit). It is rewritten only if that loses
neither text nor formatting:

- a content whose **text** would change is never rewritten;
- a content which would lose **formatting** is left as it is, unless the loss is accepted: `--accept=indent` accepts a
  category of the audit, `--accept=decision` a whole family (`convertible`, `decision`, `unclassified`). What has no
  visible effect never blocks;
- a content which is already as the editor writes it is left alone.

The report counts the contents of each source by outcome, and tells which category blocks the ones which are left. The
colors and sizes CKEditor sets inline are only converted with `--legacy-styles` (see [palette.md](palette.md)).

Every rewritten content is **saved first** under the identifier of the migration, shown at the end.
`--rollback` puts the original contents back, except the ones which have been edited since (they are reported, not
overwritten). A content is also never overwritten if it was edited between its reading and its writing.

## Where the contents are

Only the sources which can be **written** are migrated: a source implementing `WritableContentSourceInterface`
(the others are reported and left alone). If your contents are in the database, you do not need to write one.

### Columns of the database (Doctrine)

When the application uses Doctrine, the bundle reads and rewrites database columns in plain SQL, whichever the entities.
Declare them with a service implementing `ContentColumnProviderInterface`:

```php
final class ArticleColumns implements ContentColumnProviderInterface
{
    public function getColumns(): iterable
    {
        yield new ContentColumn('article', 'body', labelColumn: 'title');
        // One content per locale in a `hstore`, a JSON document with contents at any depth…
        yield new ContentColumn('text', 'text', 'hstore', labelColumn: 'technical_reference');
        yield new ContentColumn('page_bloc', 'content', ContentColumn::STORAGE_JSON, group: 'blocks');
    }
}
```

These columns are audited too. The storages the bundle knows are `scalar` (one content per row, the default) and
`json` (contents scattered in a JSON document; the path of a content is the keys which lead to it, `items.0.body`). It
works on PostgreSQL, MySQL, MariaDB and SQLite. The rows are identified by a single column (`id` by default).

### Another storage

Implement `ColumnStorageInterface` and the service is picked up (tag `umanit_tiptap.column_storage`): its
`getName()` is what you give as the storage of a column. For instance, the `hstore` of PostgreSQL:

```php
final class HstoreColumnStorage implements ColumnStorageInterface
{
    public function getName(): string {
        return 'hstore';
    }

    public function getSelectExpression(Connection $connection, string $quotedColumn): string
    {
        return \sprintf('hstore_to_json(%s)', $quotedColumn);
    }

    public function decode(string $raw): array {
        return json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
    }

    public function write(Connection $connection, ContentColumn $column, string $id, array $changes): array
    {
        // UPDATE … SET col = col || hstore(:path, :after) WHERE id = :id AND (col -> :path) = :before
    }
}
```

For anything else (files, an API…), implement `WritableContentSourceInterface` in a source provider, as for the audit.

## The backup

The contents are saved before they are rewritten, through `MigrationBackupInterface`. When the application uses
Doctrine, the bundle provides one which stores them in the table `umanit_tiptap_migration_backup`.

**That table is created by a Doctrine migration**, like any other: it is mapped by the bundle (when the ORM is
installed), so `doctrine:migrations:diff` writes the migration, and `doctrine:schema:validate` does not mistake it for a
stray table. `umanit:tiptap:migrate --force` refuses to start until it exists.

Without Doctrine, define a service `umanit_tiptap.migration.backup` implementing `MigrationBackupInterface`. The command
can still simulate without a backup.

The contents are saved before they are written, not in the same transaction: should the process stop in between, the
backup of a content which has not been rewritten is harmless, as a rollback only restores a content which is still the
migrated one.

## Tests

```bash
php vendor/bin/phpunit tests/Unit/Migration tests/Unit/Command/MigrateCommandTest.php tests/Integration/Doctrine
```
