<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Backup;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Umanit\TiptapBundle\Doctrine\Entity\BackupRecord;
use Umanit\TiptapBundle\Doctrine\Value;
use Umanit\TiptapBundle\Migration\Backup\BackupEntry;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;

/**
 * Saves the contents in the table of {@see BackupRecord}, which the application creates with a Doctrine migration.
 */
final readonly class DoctrineMigrationBackup implements MigrationBackupInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public function isReady(): bool
    {
        return $this->getConnection()->createSchemaManager()->tablesExist([BackupRecord::TABLE]);
    }

    public function record(
        string $migrationId,
        string $source,
        string $id,
        string $path,
        string $before,
        string $after,
    ): void {
        $this->getConnection()->insert(
            BackupRecord::TABLE,
            [
                'id'           => bin2hex(random_bytes(16)),
                'migration_id' => $migrationId,
                'source'       => $source,
                'entity_id'    => $id,
                'path'         => $path,
                'html_before'  => $before,
                'html_after'   => $after,
                'migrated_at'  => new \DateTimeImmutable(),
            ],
            ['migrated_at' => Types::DATETIME_IMMUTABLE],
        );
    }

    public function forget(string $migrationId, string $source, string $id, string $path): void
    {
        $this->getConnection()->delete(
            BackupRecord::TABLE,
            ['migration_id' => $migrationId, 'source' => $source, 'entity_id' => $id, 'path' => $path],
        );
    }

    public function findToRestore(string $migrationId): array
    {
        $connection = $this->getConnection();
        $entries = [];

        $rows = $connection->fetchAllAssociative(
            \sprintf(
                'SELECT id, source, entity_id, path, html_before, html_after FROM %s'
                . ' WHERE migration_id = :migration AND rolled_back_at IS NULL ORDER BY source, entity_id, path',
                $connection->getDatabasePlatform()->quoteSingleIdentifier(BackupRecord::TABLE),
            ),
            ['migration' => $migrationId],
        );

        foreach ($rows as $row) {
            $entries[] = new BackupEntry(
                Value::toString($row['id']),
                Value::toString($row['source']),
                Value::toString($row['entity_id']),
                Value::toString($row['path']),
                Value::toString($row['html_before']),
                Value::toString($row['html_after']),
            );
        }

        return $entries;
    }

    public function markRestored(BackupEntry $entry): void
    {
        $this->getConnection()->update(
            BackupRecord::TABLE,
            ['rolled_back_at' => new \DateTimeImmutable()],
            ['id' => $entry->key],
            ['rolled_back_at' => Types::DATETIME_IMMUTABLE],
        );
    }

    public function getHistory(): array
    {
        $connection = $this->getConnection();
        $history = [];

        $rows = $connection->fetchAllAssociative(
            \sprintf(
                'SELECT migration_id, COUNT(*) AS contents, COUNT(rolled_back_at) AS restored,'
                . ' MIN(migrated_at) AS migrated_at FROM %1$s GROUP BY migration_id ORDER BY MIN(migrated_at)',
                $connection->getDatabasePlatform()->quoteSingleIdentifier(BackupRecord::TABLE),
            ),
        );

        foreach ($rows as $row) {
            $history[] = [
                'migration_id' => Value::toString($row['migration_id']),
                'contents'     => Value::toInt($row['contents']),
                'restored'     => Value::toInt($row['restored']),
                'migrated_at'  => Value::toString($row['migrated_at']),
            ];
        }

        return $history;
    }

    private function getConnection(): Connection
    {
        $connection = $this->registry->getConnection();

        if (!$connection instanceof Connection) {
            throw new \LogicException('The default connection is not a DBAL connection.');
        }

        return $connection;
    }
}
