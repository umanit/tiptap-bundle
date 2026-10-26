<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\RoundTrip\NodeRoundTripRunner;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;
use Umanit\TiptapBundle\Cleaning\ContentCleaner;
use Umanit\TiptapBundle\Cleaning\HtmlCleaner;
use Umanit\TiptapBundle\Cleaning\Rule\EmptyInlineRule;
use Umanit\TiptapBundle\Cleaning\Rule\EmptyParagraphsRule;
use Umanit\TiptapBundle\Cleaning\Rule\FontTagRule;
use Umanit\TiptapBundle\Cleaning\Rule\ListValueRule;
use Umanit\TiptapBundle\Cleaning\Rule\NbspRunsRule;
use Umanit\TiptapBundle\Cleaning\Rule\NestedSpansRule;
use Umanit\TiptapBundle\Cleaning\Rule\SmallIndentRule;
use Umanit\TiptapBundle\Cleaning\Rule\TableLayoutRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordClassesRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordLanguageRule;
use Umanit\TiptapBundle\Cleaning\Rule\WordStylesRule;
use Umanit\TiptapBundle\Command\AuditCommand;
use Umanit\TiptapBundle\Command\CleanCommand;
use Umanit\TiptapBundle\Command\MigrateCommand;
use Umanit\TiptapBundle\Command\PaletteCommand;
use Umanit\TiptapBundle\Migration\ContentMigrator;
use Umanit\TiptapBundle\Palette\PaletteBuilder;
use Umanit\TiptapBundle\Palette\PaletteRenderer;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

// The audit, migration, cleaning and palette of existing contents. They run the editor under Node, through Symfony
// Process: loaded only when it is installed.

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

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
    // The cleaning rules, applied by decreasing priority: an application adds its own with the tag
    // `umanit_tiptap.cleaning_rule` (the lower the priority, the later it runs)
    $cleaningRules = [
        'word_language'    => WordLanguageRule::class,
        'word_classes'     => WordClassesRule::class,
        'font_tag'         => FontTagRule::class,
        'word_styles'      => WordStylesRule::class,
        'table_layout'     => TableLayoutRule::class,
        'small_indent'     => SmallIndentRule::class,
        'list_value'       => ListValueRule::class,
        'nested_spans'     => NestedSpansRule::class,
        'empty_inline'     => EmptyInlineRule::class,
        'nbsp_runs'        => NbspRunsRule::class,
        'empty_paragraphs' => EmptyParagraphsRule::class,
    ];
    $priority = 100;

    foreach ($cleaningRules as $name => $class) {
        $services
            ->set('umanit_tiptap.cleaning.rule.' . $name, $class)
            ->tag('umanit_tiptap.cleaning_rule', ['priority' => $priority--])
        ;
    }

    $services
        ->set('umanit_tiptap.cleaning.html_cleaner', HtmlCleaner::class)
        ->args([tagged_iterator('umanit_tiptap.cleaning_rule')])
    ;
    $services
        ->set('umanit_tiptap.cleaning.content_cleaner', ContentCleaner::class)
        ->args([
            service('umanit_tiptap.cleaning.html_cleaner'),
            service('umanit_tiptap.audit.content_source_registry'),
            service('umanit_tiptap.migration.backup')->nullOnInvalid(),
        ])
    ;
    $services
        ->set('umanit_tiptap.cleaning.command', CleanCommand::class)
        ->args([
            service('umanit_tiptap.audit.content_source_registry'),
            service('umanit_tiptap.cleaning.content_cleaner'),
            service('umanit_tiptap.cleaning.html_cleaner'),
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
};
