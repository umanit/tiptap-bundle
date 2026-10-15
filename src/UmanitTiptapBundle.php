<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class UmanitTiptapBundle extends AbstractBundle
{
    public const string ICON_PREFIX = 'umanit-tiptap';

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
