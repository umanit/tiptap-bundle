<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Process\Process;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\RoundTrip\NodeRoundTripRunner;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;
use Umanit\TiptapBundle\Builder\DefaultColorsSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultStylesSetsBuilder;
use Umanit\TiptapBundle\Builder\DefaultTemplatesSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultToolbarBuilder;
use Umanit\TiptapBundle\Command\AuditCommand;
use Umanit\TiptapBundle\Command\MigrateCommand;
use Umanit\TiptapBundle\Command\PaletteCommand;
use Umanit\TiptapBundle\Form\Type\TiptapType;
use Umanit\TiptapBundle\Migration\ContentMigrator;
use Umanit\TiptapBundle\Palette\PaletteBuilder;
use Umanit\TiptapBundle\Palette\PaletteRenderer;
use Umanit\TiptapBundle\Provider\ColorsSetProvider;
use Umanit\TiptapBundle\Provider\ColorsSetProviderInterface;
use Umanit\TiptapBundle\Provider\StylesSetProvider;
use Umanit\TiptapBundle\Provider\StylesSetProviderInterface;
use Umanit\TiptapBundle\Provider\TemplatesSetProvider;
use Umanit\TiptapBundle\Provider\TemplatesSetProviderInterface;
use Umanit\TiptapBundle\Provider\ToolbarProvider;
use Umanit\TiptapBundle\Provider\ToolbarProviderInterface;
use Umanit\TiptapBundle\Twig\Components\Color;
use Umanit\TiptapBundle\Twig\Components\CommandTool;
use Umanit\TiptapBundle\Twig\Components\ContextMenu\Table as ContextMenuTable;
use Umanit\TiptapBundle\Twig\Components\Editor;
use Umanit\TiptapBundle\Twig\Components\FontSize;
use Umanit\TiptapBundle\Twig\Components\Modal\Anchor as ModalAnchor;
use Umanit\TiptapBundle\Twig\Components\Modal\Diff as ModalDiff;
use Umanit\TiptapBundle\Twig\Components\Modal\Image as ModalImage;
use Umanit\TiptapBundle\Twig\Components\Modal\Link as ModalLink;
use Umanit\TiptapBundle\Twig\Components\Modal\Table as ModalTable;
use Umanit\TiptapBundle\Twig\Components\Modal\Template as ModalTemplate;
use Umanit\TiptapBundle\Twig\Components\Source;
use Umanit\TiptapBundle\Twig\Components\Styles;
use Umanit\TiptapBundle\Twig\Components\Tool;
use Umanit\TiptapBundle\Twig\Components\Toolbar;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Default builders: the lowest priority, an application builder with the same name takes precedence over them
    $services
        ->set('umanit_tiptap.builder.default_toolbar', DefaultToolbarBuilder::class)
        ->tag('umanit_tiptap.toolbar_builder')
    ;
    $services
        ->set('umanit_tiptap.builder.default_styles_sets', DefaultStylesSetsBuilder::class)
        ->tag('umanit_tiptap.styles_sets_builder')
    ;
    $services
        ->set('umanit_tiptap.builder.default_templates_set', DefaultTemplatesSetBuilder::class)
        ->tag('umanit_tiptap.templates_set_builder')
    ;
    $services
        ->set('umanit_tiptap.builder.default_colors_set', DefaultColorsSetBuilder::class)
        ->tag('umanit_tiptap.colors_set_builder')
    ;

    // Providers: the interfaces are aliased so that an application can inject them
    $services
        ->set('umanit_tiptap.provider.toolbar', ToolbarProvider::class)
        ->args([tagged_iterator('umanit_tiptap.toolbar_builder')])
        ->alias(ToolbarProviderInterface::class, 'umanit_tiptap.provider.toolbar')
    ;

    $services
        ->set('umanit_tiptap.provider.styles_set', StylesSetProvider::class)
        ->args([tagged_iterator('umanit_tiptap.styles_sets_builder')])
        ->alias(StylesSetProviderInterface::class, 'umanit_tiptap.provider.styles_set')
    ;

    $services
        ->set('umanit_tiptap.provider.templates_set', TemplatesSetProvider::class)
        ->args([tagged_iterator('umanit_tiptap.templates_set_builder')])
        ->alias(TemplatesSetProviderInterface::class, 'umanit_tiptap.provider.templates_set')
    ;

    $services
        ->set('umanit_tiptap.provider.colors_set', ColorsSetProvider::class)
        ->args([tagged_iterator('umanit_tiptap.colors_set_builder')])
        ->alias(ColorsSetProviderInterface::class, 'umanit_tiptap.provider.colors_set')
    ;

    // Twig components: the name and the template of a component are read from its `#[AsTwigComponent]`, as the
    // autoconfiguration of Symfony UX does. The components are not shared: Symfony UX takes care of it.
    $components = [
        'color'              => [Color::class, 'umanit_tiptap.provider.colors_set'],
        'command_tool'       => [CommandTool::class, null],
        'context_menu_table' => [ContextMenuTable::class, null],
        'editor'             => [Editor::class, 'umanit_tiptap.provider.toolbar'],
        'font_size'          => [FontSize::class, null],
        'modal_anchor'       => [ModalAnchor::class, null],
        'modal_diff'         => [ModalDiff::class, null],
        'modal_image'        => [ModalImage::class, null],
        'modal_link'         => [ModalLink::class, null],
        'modal_table'        => [ModalTable::class, 'umanit_tiptap.provider.colors_set'],
        'modal_template'     => [ModalTemplate::class, 'umanit_tiptap.provider.templates_set'],
        'source'             => [Source::class, null],
        'styles'             => [Styles::class, 'umanit_tiptap.provider.styles_set'],
        'tool'               => [Tool::class, null],
        'toolbar'            => [Toolbar::class, 'umanit_tiptap.provider.toolbar'],
    ];

    foreach ($components as $name => [$class, $provider]) {
        $attribute = new ReflectionClass($class)->getAttributes(AsTwigComponent::class)[0]->newInstance();

        $services
            ->set('umanit_tiptap.twig_component.' . $name, $class)
            ->args(null === $provider ? [] : [service($provider)])
            ->tag('twig.component', array_filter($attribute->serviceConfig()))
        ;
    }

    $services
        ->set('umanit_tiptap.form.type.tiptap', TiptapType::class)
        ->args([param('umanit_tiptap.migration_diff')])
        ->tag('form.type')
    ;

    // The audit of existing contents runs the editor under Node, through Symfony Process
    if (class_exists(Process::class)) {
        $services
            ->set('umanit_tiptap.audit.loss_rule.ckeditor', CkeditorLossRule::class)
            ->tag('umanit_tiptap.audit_loss_rule')
        ;
        $services
            ->set('umanit_tiptap.audit.loss_classifier', LossClassifier::class)
            ->args([tagged_iterator('umanit_tiptap.audit_loss_rule')])
        ;
        $services->set('umanit_tiptap.audit.html_content_extractor', HtmlContentExtractor::class);
        $services
            ->set('umanit_tiptap.audit.content_source_registry', ContentSourceRegistry::class)
            ->args([tagged_iterator('umanit_tiptap.audit_source_provider')])
        ;
        $services
            ->set('umanit_tiptap.audit.round_trip_runner', NodeRoundTripRunner::class)
            ->args([dirname(__DIR__) . '/assets/bin/roundtrip.js', param('umanit_tiptap.audit.node_command')])
        ;
        $services
            ->set('umanit_tiptap.audit.command', AuditCommand::class)
            ->args([
                service('umanit_tiptap.audit.content_source_registry'),
                service('umanit_tiptap.audit.round_trip_runner'),
                service('umanit_tiptap.audit.loss_classifier'),
            ])
            ->tag('console.command')
        ;
        // The backup is optional: the Doctrine services of the bundle define it, an application can define its own
        $services
            ->set('umanit_tiptap.migration.migrator', ContentMigrator::class)
            ->args([
                service('umanit_tiptap.audit.round_trip_runner'),
                service('umanit_tiptap.audit.loss_classifier'),
                service('umanit_tiptap.audit.content_source_registry'),
                service('umanit_tiptap.migration.backup')->nullOnInvalid(),
            ])
        ;
        $services
            ->set('umanit_tiptap.migration.command', MigrateCommand::class)
            ->args([
                service('umanit_tiptap.audit.content_source_registry'),
                service('umanit_tiptap.migration.migrator'),
                service('umanit_tiptap.migration.backup')->nullOnInvalid(),
            ])
            ->tag('console.command')
        ;
        $services->set('umanit_tiptap.palette.builder', PaletteBuilder::class);
        $services->set('umanit_tiptap.palette.renderer', PaletteRenderer::class);
        $services
            ->set('umanit_tiptap.palette.command', PaletteCommand::class)
            ->args([
                service('umanit_tiptap.audit.content_source_registry'),
                service('umanit_tiptap.audit.round_trip_runner'),
                service('umanit_tiptap.palette.builder'),
                service('umanit_tiptap.palette.renderer'),
            ])
            ->tag('console.command')
        ;
    }
};
