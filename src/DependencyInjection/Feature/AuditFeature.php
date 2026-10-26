<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DependencyInjection\Feature;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Process\Process;
use Umanit\TiptapBundle\Audit\Classification\LossRuleInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;

/**
 * The audit, migration, cleaning and palette of existing contents: they run the editor under Node, through Symfony
 * Process, and are only loaded when it is installed.
 *
 * @internal
 */
final readonly class AuditFeature implements FeatureInterface
{
    public static function isAvailable(): bool
    {
        return class_exists(Process::class);
    }

    public function prepend(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
    }

    public function isEnabled(ContainerBuilder $container): bool
    {
        return self::isAvailable();
    }

    public function load(ContainerConfigurator $configurator): void
    {
        $configurator->import(\dirname(__DIR__, 3) . '/config/audit.php');
    }

    public function getAutoconfiguration(): array
    {
        return [
            ContentSourceProviderInterface::class => 'umanit_tiptap.audit_source_provider',
            LossRuleInterface::class              => 'umanit_tiptap.audit_loss_rule',
        ];
    }
}
