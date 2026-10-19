<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine;

use Doctrine\ORM\Mapping\Entity;
use Umanit\TiptapBundle\Doctrine\Entity\BackupRecord;

/**
 * The mapping of the table where the migration saves the contents it rewrites, for applications which use the ORM.
 */
final class OrmMapping
{
    public static function isAvailable(): bool
    {
        return class_exists(Entity::class);
    }

    /**
     * Configuration of `doctrine.orm.mappings`, to prepend to the one of the application.
     *
     * @return array<string, array<string, string|bool>>
     */
    public static function getMappings(): array
    {
        return [
            'UmanitTiptapBundle' => [
                'type'      => 'attribute',
                'is_bundle' => false,
                'dir'       => \dirname((string) new \ReflectionClass(BackupRecord::class)->getFileName()),
                'prefix'    => new \ReflectionClass(BackupRecord::class)->getNamespaceName(),
                'alias'     => 'UmanitTiptap',
            ],
        ];
    }
}
