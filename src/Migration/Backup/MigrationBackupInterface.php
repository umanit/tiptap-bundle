<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration\Backup;

/**
 * Where the contents are saved before the migration rewrites them, to be able to undo it.
 *
 * The bundle provides an implementation when the application uses Doctrine; an application without it, or which
 * prefers another storage, defines its own service `umanit_tiptap.migration.backup`.
 */
interface MigrationBackupInterface
{
    /**
     * Whether the storage can be written (its table exists…): when it cannot, the migration does not start.
     */
    public function isReady(): bool;

    /**
     * Saves a content: called before it is rewritten.
     */
    public function record(
        string $migrationId,
        string $source,
        string $id,
        string $path,
        string $before,
        string $after,
    ): void;

    /**
     * Forgets a content which has finally not been rewritten (it was edited in the meantime).
     */
    public function forget(string $migrationId, string $source, string $id, string $path): void;

    /**
     * The contents of a migration which have not been restored yet.
     *
     * @return list<BackupEntry>
     */
    public function findToRestore(string $migrationId): array;

    public function markRestored(BackupEntry $entry): void;

    /**
     * @return list<array{migration_id: string, contents: int, restored: int, migrated_at: string}>
     */
    public function getHistory(): array;
}
