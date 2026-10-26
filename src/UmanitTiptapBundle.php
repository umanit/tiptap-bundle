<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Umanit\TiptapBundle\DependencyInjection\Feature\AuditFeature;
use Umanit\TiptapBundle\DependencyInjection\Feature\DoctrineFeature;
use Umanit\TiptapBundle\DependencyInjection\Feature\EditorFeature;
use Umanit\TiptapBundle\DependencyInjection\Feature\FeatureInterface;

class UmanitTiptapBundle extends AbstractBundle
{
    public const string ICON_PREFIX = 'umanit-tiptap';
    public const string NPM_PACKAGE = '@umanit/tiptap-bundle';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('migration_diff')
                    ->defaultFalse()
                    ->info(
                        'Warns when a field loads a content the editor rewrites, and shows the difference. '
                        . 'For the environments where a migration is checked, not for production.',
                    )
                ->end()
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
     * @param array{migration_diff: bool, audit: array{node_command: string}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->parameters()->set('umanit_tiptap.migration_diff', $config['migration_diff']);
        $configurator->parameters()->set('umanit_tiptap.audit.node_command', $config['audit']['node_command']);

        foreach ($this->getFeatures() as $feature) {
            if (!$feature->isEnabled($container)) {
                continue;
            }

            $feature->load($configurator);

            // The application services implementing these interfaces are picked up by the bundle
            foreach ($feature->getAutoconfiguration() as $interface => $tag) {
                $container->registerForAutoconfiguration($interface)->addTag($tag);
            }
        }
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        foreach ($this->getFeatures() as $feature) {
            $feature->prepend($configurator, $container);
        }
    }

    /**
     * @return list<FeatureInterface> In the order they are loaded: the Doctrine one relies on the audit
     */
    private function getFeatures(): array
    {
        return [new EditorFeature($this->getPath()), new AuditFeature(), new DoctrineFeature()];
    }
}
