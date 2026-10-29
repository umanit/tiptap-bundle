<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\BrandLossRule;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FixtureContentSourceProvider;
use Umanit\TiptapBundle\UmanitTiptapBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    protected bool $withDoctrine = false;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new UmanitTiptapBundle();

        if ($this->withDoctrine) {
            yield new DoctrineBundle();
            yield new DoctrineMigrationsBundle();
        }
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getTestsDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getTestsDir() . '/log';
    }

    /**
     * Specific to this checkout: the compiled container holds absolute paths, so another copy of the bundle on the
     * same machine must not reuse it.
     */
    protected function getTestsDir(): string
    {
        return sys_get_temp_dir() . '/umanit_tiptap_tests/' . md5(__DIR__);
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret'                => 'test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'form'                  => ['enabled' => true],
            'default_locale'        => 'en',
            'translator'            => ['enabled' => true],
        ]);
        $container->register('logger', NullLogger::class);
        $container->register(CompactToolbarBuilder::class)->setAutoconfigured(true)->setAutowired(true);
        $container->register(CustomToolToolbarBuilder::class)->setAutoconfigured(true)->setAutowired(true);
        $container->register(CustomToolsBuilder::class)->setAutoconfigured(true);
        $container->register(OverridingStylesSetsBuilder::class)->setAutoconfigured(true);
        $container->register(FixtureContentSourceProvider::class)->setAutoconfigured(true);
        $container->register(BrandLossRule::class)->setAutoconfigured(true);
        $container->setAlias('test.form.factory', 'form.factory')->setPublic(true);
        $container->loadFromExtension('twig_component', [
            'defaults'                     => [],
            'anonymous_template_directory' => 'components',
        ]);
        $container->loadFromExtension('twig', ['default_path' => __DIR__ . '/templates']);
    }
}
