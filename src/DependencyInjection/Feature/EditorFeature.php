<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DependencyInjection\Feature;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Umanit\TiptapBundle\Builder\ColorsSetBuilderInterface;
use Umanit\TiptapBundle\Builder\StylesSetsBuilderInterface;
use Umanit\TiptapBundle\Builder\TemplatesSetBuilderInterface;
use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\UmanitTiptapBundle;

/**
 * The editor: builders, providers, Twig components and form type. Always loaded.
 *
 * @internal
 */
final readonly class EditorFeature implements FeatureInterface
{
    /**
     * @param string $bundlePath Directory of the bundle
     */
    public function __construct(
        private string $bundlePath,
    ) {
    }

    public function prepend(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig')) {
            $configurator->extension('twig', [
                'form_themes' => ['@UmanitTiptap/form/theme.html.twig'],
            ]);
        }

        if ($container->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            // Only the JS sources and the CSS are exposed: `node_modules` and the tooling files stay out of the map.
            $configurator->extension('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $this->bundlePath . '/assets/src'  => UmanitTiptapBundle::NPM_PACKAGE . '/src',
                        $this->bundlePath . '/assets/dist' => UmanitTiptapBundle::NPM_PACKAGE . '/dist',
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('ux_icons')) {
            // The editor icons (Lucide, ISC license) are embedded: no Iconify call, no `ux:icons:import` needed.
            $configurator->extension('ux_icons', [
                'icon_sets' => [
                    UmanitTiptapBundle::ICON_PREFIX => ['path' => $this->bundlePath . '/icons'],
                ],
            ]);
        }
    }

    public function isEnabled(ContainerBuilder $container): bool
    {
        return true;
    }

    public function load(ContainerConfigurator $configurator): void
    {
        $configurator->import(\dirname(__DIR__, 3) . '/config/editor.php');
    }

    public function getAutoconfiguration(): array
    {
        return [
            ToolbarBuilderInterface::class      => 'umanit_tiptap.toolbar_builder',
            StylesSetsBuilderInterface::class   => 'umanit_tiptap.styles_sets_builder',
            TemplatesSetBuilderInterface::class => 'umanit_tiptap.templates_set_builder',
            ColorsSetBuilderInterface::class    => 'umanit_tiptap.colors_set_builder',
        ];
    }
}
