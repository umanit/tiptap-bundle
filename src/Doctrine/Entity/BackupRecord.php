<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A content saved before the migration rewrote it.
 *
 * The class is only there for the mapping: when the application uses the ORM, `doctrine:migrations:diff` creates the
 * table like any other and `doctrine:schema:validate` does not mistake it for a stray one. The backup itself is read
 * and written in SQL (see `DoctrineMigrationBackup`), which gives a row the same identifier as this constructor does.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'idx_umanit_tiptap_migration_backup_migration', columns: ['migration_id'])]
class BackupRecord
{
    public const string TABLE = 'umanit_tiptap_migration_backup';

    // Enough for the longest text of every platform (`LONGTEXT` on MySQL)
    private const int TEXT_LENGTH = 4294967295;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $id;

    #[ORM\Column(name: 'migrated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $migratedAt;

    #[ORM\Column(name: 'rolled_back_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rolledBackAt = null;

    public function __construct(
        #[ORM\Column(name: 'migration_id', type: Types::STRING, length: 40)]
        private string $migrationId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $source,
        #[ORM\Column(name: 'entity_id', type: Types::STRING, length: 255)]
        private string $entityId,
        #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => ''])]
        private string $path,
        #[ORM\Column(name: 'html_before', type: Types::TEXT, length: self::TEXT_LENGTH)]
        private string $htmlBefore,
        #[ORM\Column(name: 'html_after', type: Types::TEXT, length: self::TEXT_LENGTH)]
        private string $htmlAfter,
    ) {
        $this->id = bin2hex(random_bytes(16));
        $this->migratedAt = new \DateTimeImmutable();
    }

    public function markRolledBack(): void
    {
        $this->rolledBackAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMigrationId(): string
    {
        return $this->migrationId;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHtmlBefore(): string
    {
        return $this->htmlBefore;
    }

    public function getHtmlAfter(): string
    {
        return $this->htmlAfter;
    }

    public function getMigratedAt(): \DateTimeImmutable
    {
        return $this->migratedAt;
    }

    public function getRolledBackAt(): ?\DateTimeImmutable
    {
        return $this->rolledBackAt;
    }
}
