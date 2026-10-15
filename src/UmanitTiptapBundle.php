<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class UmanitTiptapBundle extends AbstractBundle
{
    public const string ICON_PREFIX = 'umanit-tiptap';
    public const string NPM_PACKAGE = '@umanit/tiptap-bundle';

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('../config/services.php');
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
