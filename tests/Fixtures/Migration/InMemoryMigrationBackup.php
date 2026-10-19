<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Migration;

use Umanit\TiptapBundle\Migration\Backup\BackupEntry;
use Umanit\TiptapBundle\Migration\Backup\MigrationBackupInterface;

final class InMemoryMigrationBackup implements MigrationBackupInterface
{
    /** @var array<string, array{migration: string, entry: BackupEntry, restored: bool}> */
    public array $records = [];

    public function __construct(
        private readonly bool $ready = true,
    ) {
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function record(
        string $migrationId,
        string $source,
        string $id,
        string $path,
        string $before,
        string $after,
    ): void {
        $key = implode('|', [$migrationId, $source, $id, $path]);

        $this->records[$key] = [
            'migration' => $migrationId,
            'entry'     => new BackupEntry($key, $source, $id, $path, $before, $after),
            'restored'  => false,
        ];
    }

    public function forget(string $migrationId, string $source, string $id, string $path): void
    {
        unset($this->records[implode('|', [$migrationId, $source, $id, $path])]);
    }

    public function findToRestore(string $migrationId): array
    {
        $entries = [];

        foreach ($this->records as $record) {
            if ($record['migration'] === $migrationId && !$record['restored']) {
                $entries[] = $record['entry'];
            }
        }

        return $entries;
    }

    public function markRestored(BackupEntry $entry): void
    {
        $this->records[$entry->key]['restored'] = true;
    }

    public function getHistory(): array
    {
        $history = [];

        foreach ($this->records as $record) {
            $id = $record['migration'];
            $history[$id] ??= ['migration_id' => $id, 'contents' => 0, 'restored' => 0, 'migrated_at' => '2026-01-01'];
            ++$history[$id]['contents'];
            $history[$id]['restored'] += $record['restored'] ? 1 : 0;
        }

        return array_values($history);
    }
}
