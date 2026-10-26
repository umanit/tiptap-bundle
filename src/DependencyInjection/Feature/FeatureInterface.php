<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DependencyInjection\Feature;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A part of the bundle which is loaded on its own conditions: its services, the interfaces of the application it
 * picks up, and what it prepends to the configuration of the other bundles.
 *
 * @internal
 */
interface FeatureInterface
{
    /**
     * Prepends to the configuration of the other bundles. The extensions of the application are known here, and not
     * when {@see self::isEnabled()} is called.
     */
    public function prepend(ContainerConfigurator $configurator, ContainerBuilder $container): void;

    public function isEnabled(ContainerBuilder $container): bool;

    public function load(ContainerConfigurator $configurator): void;

    /**
     * The services of the application which implement one of these interfaces are tagged.
     *
     * @return array<class-string, string> Tag by interface
     */
    public function getAutoconfiguration(): array;
}
