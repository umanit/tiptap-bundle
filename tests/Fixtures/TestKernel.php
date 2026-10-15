<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Umanit\TiptapBundle\UmanitTiptapBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new UmanitTiptapBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/umanit_tiptap_tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/umanit_tiptap_tests/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret'     => 'test',
            'test'       => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form'       => ['enabled' => true],
            'default_locale' => 'en',
            'translator' => ['enabled' => true],
        ]);
        $container->register(CompactToolbarBuilder::class)->setAutoconfigured(true);
        $container->setAlias('test.form.factory', 'form.factory')->setPublic(true);
        $container->loadFromExtension('twig', ['default_path' => __DIR__ . '/templates']);
    }
}
