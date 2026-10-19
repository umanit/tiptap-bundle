<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration\Backup;

/**
 * A content saved before the migration rewrote it.
 */
final readonly class BackupEntry
{
    /**
     * @param string $key Identifies the entry in the storage, to mark it as restored
     */
    public function __construct(
        public string $key,
        public string $source,
        public string $id,
        public string $path,
        public string $before,
        public string $after,
    ) {
    }
}
