<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Umanit\TiptapBundle\Doctrine\Backup\DoctrineMigrationBackup;
use Umanit\TiptapBundle\Doctrine\ContentColumnSourceProvider;
use Umanit\TiptapBundle\Doctrine\Storage\ColumnStorageRegistry;
use Umanit\TiptapBundle\Doctrine\Storage\JsonColumnStorage;
use Umanit\TiptapBundle\Doctrine\Storage\ScalarColumnStorage;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

// Loaded only when the application uses Doctrine: the columns of the database which hold contents are audited and
// migrated, and the migration saves the contents it rewrites in a table.

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The storages of the columns: an application adds its own by tagging a `ColumnStorageInterface`
    $services
        ->set('umanit_tiptap.doctrine.storage.scalar', ScalarColumnStorage::class)
        ->tag('umanit_tiptap.column_storage')
    ;
    $services
        ->set('umanit_tiptap.doctrine.storage.json', JsonColumnStorage::class)
        ->tag('umanit_tiptap.column_storage')
    ;
    $services
        ->set('umanit_tiptap.doctrine.storage_registry', ColumnStorageRegistry::class)
        ->args([tagged_iterator('umanit_tiptap.column_storage')])
    ;

    $services
        ->set('umanit_tiptap.doctrine.content_column_source_provider', ContentColumnSourceProvider::class)
        ->args([
            tagged_iterator('umanit_tiptap.content_column_provider'),
            service('umanit_tiptap.doctrine.storage_registry'),
            service('doctrine'),
            service('umanit_tiptap.audit.html_content_extractor'),
        ])
        ->tag('umanit_tiptap.audit_source_provider')
    ;

    $services
        ->set('umanit_tiptap.migration.backup', DoctrineMigrationBackup::class)
        ->args([service('doctrine')])
    ;
};
