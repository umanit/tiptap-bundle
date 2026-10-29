<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DependencyInjection\Feature;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Umanit\TiptapBundle\Doctrine\ContentColumnProviderInterface;
use Umanit\TiptapBundle\Doctrine\Migrations\Version20261029000000;
use Umanit\TiptapBundle\Doctrine\OrmMapping;
use Umanit\TiptapBundle\Doctrine\Storage\ColumnStorageInterface;

/**
 * The columns of the database are audited and migrated, and the migration saves the contents it rewrites in a table,
 * when the application uses Doctrine (and the audit is available: the columns are sources of it).
 *
 * @internal
 */
final readonly class DoctrineFeature implements FeatureInterface
{
    /**
     * Set while the extensions of the application are known ({@see self::prepend()}): the extension of the bundle only
     * sees its own when it loads.
     */
    private const string PARAMETER_USES_DOCTRINE = 'umanit_tiptap.uses_doctrine';

    public function prepend(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $usesDoctrine = $container->hasExtension('doctrine');

        $container->setParameter(self::PARAMETER_USES_DOCTRINE, $usesDoctrine);

        // The table where the migration saves the contents it rewrites is mapped, so that Doctrine knows about it
        if ($usesDoctrine && OrmMapping::isAvailable() && AuditFeature::isAvailable()) {
            $configurator->extension('doctrine', ['orm' => ['mappings' => OrmMapping::getMappings()]]);
        }

        // ... and its migration is registered next to the ones of the application, which only has to run them
        if ($usesDoctrine && $container->hasExtension('doctrine_migrations') && AuditFeature::isAvailable()) {
            $migration = new \ReflectionClass(Version20261029000000::class);

            $configurator->extension('doctrine_migrations', [
                'migrations_paths' => [$migration->getNamespaceName() => \dirname((string) $migration->getFileName())],
            ]);
        }
    }

    public function isEnabled(ContainerBuilder $container): bool
    {
        return AuditFeature::isAvailable() && true === $container->getParameter(self::PARAMETER_USES_DOCTRINE);
    }

    public function load(ContainerConfigurator $configurator): void
    {
        $configurator->import(\dirname(__DIR__, 3) . '/config/doctrine.php');
    }

    public function getAutoconfiguration(): array
    {
        return [
            ContentColumnProviderInterface::class => 'umanit_tiptap.content_column_provider',
            ColumnStorageInterface::class         => 'umanit_tiptap.column_storage',
        ];
    }
}
