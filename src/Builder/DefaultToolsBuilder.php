<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\Twig\Components\ColorType;
use Umanit\TiptapBundle\UmanitTiptapBundle;

/**
 * The tools of the bundle. Lowest priority: an application builder which lists a tool with the same name takes
 * precedence over it.
 */
#[AsTaggedItem(priority: -100)]
final readonly class DefaultToolsBuilder implements ToolsBuilderInterface
{
    /**
     * @param string|array<string, mixed>|null $args
     * @param array<string, mixed>|null        $active
     */
    private static function button(
        string $name,
        string $key,
        string $icon,
        string $command,
        string|array|null $args = null,
        ?string $label = null,
        ?array $active = null,
    ): Tool {
        return new Tool(
            $name,
            'wysiwyg.' . $key,
            command: $command,
            icon: self::icon($icon),
            label: null === $label ? null : 'wysiwyg.' . $label,
            args: $args,
            active: $active,
        );
    }

    private static function icon(string $name): string
    {
        return UmanitTiptapBundle::ICON_PREFIX . ':' . $name;
    }

    public function build(): iterable
    {
        yield from $this->history();
        yield from $this->clipboard();
        yield from $this->blocks();
        yield from $this->marks();
        yield from $this->alignment();
        yield from $this->lists();
        yield from $this->links();
        yield from $this->inserts();
        yield from $this->composites();
    }

    /**
     * @return iterable<Tool>
     */
    private function history(): iterable
    {
        yield self::button('undo', 'toolbar.undo', 'undo', 'undo', label: 'toolbar.undo_text');
        yield self::button('redo', 'toolbar.redo', 'redo', 'redo', label: 'toolbar.redo_text');
    }

    /**
     * @return iterable<Tool>
     */
    private function clipboard(): iterable
    {
        yield self::button('cut', 'toolbar.cut', 'scissors', 'clipboard.cut');
        yield self::button('copy', 'toolbar.copy', 'copy', 'clipboard.copy');
        yield self::button('paste', 'toolbar.paste', 'clipboard-paste', 'clipboard.paste');
    }

    /**
     * @return iterable<Tool>
     */
    private function blocks(): iterable
    {
        yield self::button(
            'paragraph',
            'toolbar.paragraph',
            'pilcrow',
            'setParagraph',
            active: ['name' => 'paragraph'],
        );

        foreach ([1, 2, 3] as $level) {
            yield self::button(
                'heading' . $level,
                'toolbar.h' . $level,
                'heading-' . $level,
                'toggleHeading',
                args: ['level' => $level],
                active: ['name' => 'heading', 'attributes' => ['level' => $level]],
            );
        }

        yield self::button(
            'blockquote',
            'toolbar.blockquote',
            'quote',
            'toggleBlockquote',
            active: ['name' => 'blockquote'],
        );
    }

    /**
     * @return iterable<Tool>
     */
    private function marks(): iterable
    {
        $marks = [
            'bold'        => ['bold', 'bold', 'toggleBold'],
            'italic'      => ['italic', 'italic', 'toggleItalic'],
            'underline'   => ['underline', 'underline', 'toggleUnderline'],
            'strike'      => ['strike', 'strikethrough', 'toggleStrike'],
            'subscript'   => ['subscript', 'subscript', 'toggleSubscript'],
            'superscript' => ['superscript', 'superscript', 'toggleSuperscript'],
        ];

        foreach ($marks as $name => [$key, $icon, $command]) {
            yield self::button($name, 'toolbar.' . $key, $icon, $command, active: ['name' => $name]);
        }
    }

    /**
     * @return iterable<Tool>
     */
    private function alignment(): iterable
    {
        foreach (['left', 'center', 'right', 'justify'] as $alignment) {
            yield self::button(
                'align' . ucfirst($alignment),
                'toolbar.align_' . $alignment,
                'align-' . $alignment,
                'toggleTextAlign',
                args: $alignment,
                active: ['attributes' => ['textAlign' => $alignment]],
            );
        }
    }

    /**
     * @return iterable<Tool>
     */
    private function lists(): iterable
    {
        yield self::button(
            'bulletList',
            'toolbar.bullet_list',
            'list',
            'toggleBulletList',
            active: ['name' => 'bulletList'],
        );
        yield self::button(
            'orderedList',
            'toolbar.ordered_list',
            'list-ordered',
            'toggleOrderedList',
            active: ['name' => 'orderedList'],
        );
        yield self::button('indent', 'toolbar.indent', 'indent', 'sinkListItem', args: 'listItem');
        yield self::button('outdent', 'toolbar.outdent', 'outdent', 'liftListItem', args: 'listItem');
        yield self::button(
            'listTight',
            'toolbar.list_tight',
            'list-chevrons-down-up',
            'toggleListTight',
            active: ['state' => 'listTight'],
        );
    }

    /**
     * @return iterable<Tool>
     */
    private function links(): iterable
    {
        yield self::button('anchor', 'toolbar.anchor', 'flag', 'anchor.open');
        yield self::button('link', 'toolbar.link', 'link', 'link.open');
        yield self::button('unlink', 'toolbar.unlink', 'link-2-off', 'link.unset');
    }

    /**
     * @return iterable<Tool>
     */
    private function inserts(): iterable
    {
        yield self::button('image', 'toolbar.image', 'image', 'image.open', active: ['name' => 'image']);
        yield self::button('table', 'toolbar.table_add', 'table', 'table.openAddModal');
        yield self::button('template', 'toolbar.insert_template', 'layout-template', 'template.open');
    }

    /**
     * What is more than a button: dropdowns and palettes, rendered by their own component.
     *
     * @return iterable<Tool>
     */
    private function composites(): iterable
    {
        yield new Tool(
            'styles',
            'wysiwyg.style.main_title',
            label: 'wysiwyg.style.button_label',
            component: 'Umanit:Tiptap:Styles',
            context: ['name' => 'stylesSet'],
        );
        yield new Tool(
            'color',
            'wysiwyg.color.choose',
            icon: self::icon('palette'),
            component: 'Umanit:Tiptap:Color',
            props: ['type' => ColorType::Text],
            context: ['name' => 'colorsSet'],
        );
        yield new Tool(
            'bgColor',
            'wysiwyg.bg_color.choose',
            icon: self::icon('paint-bucket'),
            component: 'Umanit:Tiptap:Color',
            props: ['type' => ColorType::Background],
            context: ['name' => 'backgroundColorsSet'],
        );
        yield new Tool(
            'fontSize',
            'wysiwyg.font_size.choose',
            icon: self::icon('a-large-small'),
            component: 'Umanit:Tiptap:FontSize',
        );
        yield new Tool(
            'source',
            'wysiwyg.toolbar.source_mode',
            icon: self::icon('code'),
            component: 'Umanit:Tiptap:Source',
        );
    }
}
