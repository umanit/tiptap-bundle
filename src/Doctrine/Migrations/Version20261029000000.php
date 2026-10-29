<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Umanit\TiptapBundle\Doctrine\Entity\BackupRecord;

/**
 * Creates the table where the migration of the contents saves what it rewrites (see {@see BackupRecord}).
 *
 * The bundle registers its directory in `doctrine_migrations.migrations_paths`, so the application runs it with its own
 * migrations. It is written with the schema API, which is the same on every platform.
 */
final class Version20261029000000 extends AbstractMigration
{
    private const int TEXT_LENGTH = 4294967295;

    public function getDescription(): string
    {
        return 'Create the table where the TipTap migration saves the contents it rewrites';
    }

    public function up(Schema $schema): void
    {
        // An application which created the table with its own migration (`doctrine:migrations:diff`) keeps it
        if ($schema->hasTable(BackupRecord::TABLE)) {
            return;
        }

        $table = $schema->createTable(BackupRecord::TABLE);
        $table->addColumn('id', Types::STRING, ['length' => 32]);
        $table->addColumn('migration_id', Types::STRING, ['length' => 40]);
        $table->addColumn('source', Types::STRING, ['length' => 255]);
        $table->addColumn('entity_id', Types::STRING, ['length' => 255]);
        $table->addColumn('path', Types::STRING, ['length' => 255, 'default' => '']);
        $table->addColumn('html_before', Types::TEXT, ['length' => self::TEXT_LENGTH]);
        $table->addColumn('html_after', Types::TEXT, ['length' => self::TEXT_LENGTH]);
        $table->addColumn('migrated_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('rolled_back_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['migration_id'], 'idx_umanit_tiptap_migration_backup_migration');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable(BackupRecord::TABLE);
    }
}
