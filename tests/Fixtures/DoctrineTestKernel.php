<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Doctrine\ORM\Configuration;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Umanit\TiptapBundle\Tests\Fixtures\Doctrine\ArticleColumnProvider;

/**
 * The kernel of an application which uses Doctrine, with a SQLite database in memory.
 */
class DoctrineTestKernel extends TestKernel
{
    protected bool $withDoctrine = true;

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/umanit_tiptap_tests/doctrine_cache';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
            // Native lazy objects need ORM 3.4 or later
            'orm'  => method_exists(Configuration::class, 'enableNativeLazyObjects')
                ? ['enable_native_lazy_objects' => true]
                : [],
        ]);
        $container->register(ArticleColumnProvider::class)->setAutoconfigured(true);
    }
}
