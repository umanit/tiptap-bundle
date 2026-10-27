<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\Stub;

/**
 * An in-memory SQLite database, with the table of the backup created from its mapping.
 */
final class SqliteDatabase
{
    public static function create(bool $withBackupTable = false): Connection
    {
        $paths = [__DIR__ . '/../../../src/Doctrine/Entity'];
        // ORM 2 names it differently, and has no native lazy objects (ORM 3.4 and later)
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig($paths)
            : ORMSetup::createAttributeMetadataConfiguration($paths);

        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        if ($withBackupTable) {
            $entityManager = new EntityManager($connection, $config);

            new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        }

        return $connection;
    }

    /**
     * @param Stub&ManagerRegistry $registry A stub of the registry, given by the test case
     */
    public static function registryOf(Connection $connection, ManagerRegistry $registry): ManagerRegistry
    {
        $registry->method('getConnection')->willReturn($connection);

        return $registry;
    }
}
