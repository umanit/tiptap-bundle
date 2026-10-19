<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Process\Process;
use Umanit\TiptapBundle\Audit\Classification\LossRuleInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;
use Umanit\TiptapBundle\Builder\ColorsSetBuilderInterface;
use Umanit\TiptapBundle\Builder\StylesSetsBuilderInterface;
use Umanit\TiptapBundle\Builder\TemplatesSetBuilderInterface;
use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;

class UmanitTiptapBundle extends AbstractBundle
{
    public const string ICON_PREFIX = 'umanit-tiptap';
    public const string NPM_PACKAGE = '@umanit/tiptap-bundle';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('audit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('node_command')
                            ->defaultValue('node')
                            ->info('Command which starts Node to audit existing contents (`yarn node` with Yarn PnP).')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{audit: array{node_command: string}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->parameters()->set('umanit_tiptap.audit.node_command', $config['audit']['node_command']);
        $configurator->import('../config/services.php');

        // The application services implementing these interfaces are picked up by the bundle
        $autoconfiguration = [
            ToolbarBuilderInterface::class      => 'umanit_tiptap.toolbar_builder',
            StylesSetsBuilderInterface::class   => 'umanit_tiptap.styles_sets_builder',
            TemplatesSetBuilderInterface::class => 'umanit_tiptap.templates_set_builder',
            ColorsSetBuilderInterface::class    => 'umanit_tiptap.colors_set_builder',
        ];

        if (class_exists(Process::class)) {
            $autoconfiguration[ContentSourceProviderInterface::class] = 'umanit_tiptap.audit_source_provider';
            $autoconfiguration[LossRuleInterface::class] = 'umanit_tiptap.audit_loss_rule';
        }

        foreach ($autoconfiguration as $interface => $tag) {
            $container->registerForAutoconfiguration($interface)->addTag($tag);
        }
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
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
                        $this->getPath() . '/assets/src'  => self::NPM_PACKAGE . '/src',
                        $this->getPath() . '/assets/dist' => self::NPM_PACKAGE . '/dist',
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('ux_icons')) {
            // The editor icons (Lucide, ISC license) are embedded: no Iconify call, no `ux:icons:import` needed.
            $configurator->extension('ux_icons', [
                'icon_sets' => [
                    self::ICON_PREFIX => ['path' => $this->getPath() . '/icons'],
                ],
            ]);
        }
    }
}
