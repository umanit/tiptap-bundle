<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Umanit\TiptapBundle\DependencyInjection\Feature\AuditFeature;
use Umanit\TiptapBundle\DependencyInjection\Feature\DoctrineFeature;
use Umanit\TiptapBundle\DependencyInjection\Feature\EditorFeature;

#[CoversClass(DoctrineFeature::class)]
#[CoversClass(AuditFeature::class)]
#[CoversClass(EditorFeature::class)]
final class FeaturesTest extends TestCase
{
    public function testItIsEnabledWhenTheApplicationUsesDoctrine(): void
    {
        $container = $this->prepended(withDoctrine: true);

        $this->assertTrue(new DoctrineFeature()->isEnabled($container));
    }

    public function testItIsDisabledWithoutDoctrine(): void
    {
        $container = $this->prepended(withDoctrine: false);

        $this->assertFalse(new DoctrineFeature()->isEnabled($container));
    }

    public function testTheEditorIsAlwaysEnabledAndPicksUpItsBuilders(): void
    {
        $editor = new EditorFeature('/bundle');

        $this->assertTrue($editor->isEnabled(new ContainerBuilder()));
        $this->assertContains('umanit_tiptap.toolbar_builder', $editor->getAutoconfiguration());
    }

    private function prepended(bool $withDoctrine): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if ($withDoctrine) {
            $container->registerExtension(new class extends Extension {
                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return 'doctrine';
                }
            });
        }

        $instanceof = [];
        $configurator = new ContainerConfigurator(
            $container,
            new PhpFileLoader($container, new FileLocator()),
            $instanceof,
            __FILE__,
            __FILE__,
        );

        new DoctrineFeature()->prepend($configurator, $container);

        return $container;
    }
}
