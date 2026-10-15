<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Umanit\TiptapBundle\Form\Type\TiptapType;
use Umanit\TiptapBundle\Provider\ColorsSetProvider;
use Umanit\TiptapBundle\Provider\ColorsSetProviderInterface;
use Umanit\TiptapBundle\Provider\StylesSetProvider;
use Umanit\TiptapBundle\Provider\StylesSetProviderInterface;
use Umanit\TiptapBundle\Provider\TemplatesSetProvider;
use Umanit\TiptapBundle\Provider\TemplatesSetProviderInterface;
use Umanit\TiptapBundle\Provider\ToolbarProvider;
use Umanit\TiptapBundle\Provider\ToolbarProviderInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    // Loading the builders directory registers the default builders and, above all, makes the container read the
    // `#[AutoconfigureTag]` of the builder interfaces: every application service implementing one gets tagged.
    $services->load('Umanit\\TiptapBundle\\Builder\\', '../src/Builder/');

    $providers = [
        [ToolbarProvider::class, ToolbarProviderInterface::class, 'umanit_tiptap.toolbar_builder'],
        [StylesSetProvider::class, StylesSetProviderInterface::class, 'umanit_tiptap.styles_sets_builder'],
        [TemplatesSetProvider::class, TemplatesSetProviderInterface::class, 'umanit_tiptap.templates_set_builder'],
        [ColorsSetProvider::class, ColorsSetProviderInterface::class, 'umanit_tiptap.colors_set_builder'],
    ];

    foreach ($providers as [$class, $interface, $tag]) {
        $services
            ->set($class)
            ->args([tagged_iterator($tag)])
            ->alias($interface, $class)
        ;
    }

    $services->load('Umanit\\TiptapBundle\\Twig\\Components\\', '../src/Twig/Components/');

    $services->set(TiptapType::class);
};
