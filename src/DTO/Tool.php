<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

/**
 * A tool of the toolbar: either a button which runs a command, or a Twig component for what is more than a button
 * (a dropdown, a palette…).
 *
 * The tools of the bundle and the ones of an application are the same thing: they are listed by the builders of tools
 * (see `ToolsBuilderInterface`) and picked by name in the builders of toolbars. See docs/customization.md.
 */
final readonly class Tool implements \Stringable
{
    /** Keys of an active state descriptor: see `assets/src/editor/tool-state.js`. */
    private const array ACTIVE_KEYS = ['name', 'attributes', 'state'];

    /**
     * @param string                           $name              Identifies the tool (`data-wysiwyg-tool`)
     * @param string                           $title             Translation key, or text already translated
     * @param string|null                      $command           TipTap command (`toggleBold`) or `<manager>.<method>`
     * @param string|null                      $icon              Name of a UX icon (`app:highlighter`)
     * @param string|null                      $label             Translation key or text; hidden when there is an icon
     * @param string|array<string, mixed>|null $args              Arguments given to the command
     * @param string|null                      $component         Twig component which renders the tool, not a button
     * @param array<string, mixed>             $props             Props of the component, besides the title, icon, label
     * @param array<string, string>            $context           Props taken from the editor, `prop => key`: the key is
     *                                                            `stylesSet`, `colorsSet` or `backgroundColorsSet`
     * @param array<string, mixed>|null        $active            When the button shows as active at the cursor: `name`
     *                                                            (and `attributes`) of a node or a mark, or a `state`
     * @param string                           $translationDomain Domain of the title and of the label
     */
    public function __construct(
        public string $name,
        public string $title,
        public ?string $command = null,
        public ?string $icon = null,
        public ?string $label = null,
        public string|array|null $args = null,
        public ?string $component = null,
        public array $props = [],
        public array $context = [],
        public ?array $active = null,
        public string $translationDomain = 'UmanitTiptapBundle',
    ) {
        if ('' === $name || '' === $title) {
            throw new \InvalidArgumentException('A tool needs a name and a title (which names its button).');
        }

        if ((null === $command) === (null === $component)) {
            throw new \InvalidArgumentException(
                \sprintf('The tool "%s" must run a command or be rendered by a component, not both nor none.', $name),
            );
        }

        if (null !== $active) {
            if (null === $command) {
                throw new \InvalidArgumentException(\sprintf('The tool "%s" has no command to be active for.', $name));
            }

            $unknown = array_diff(array_keys($active), self::ACTIVE_KEYS);

            if ([] !== $unknown) {
                throw new \InvalidArgumentException(
                    \sprintf('The active state of the tool "%s" has unknown keys: %s.', $name, implode(', ', $unknown)),
                );
            }
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @param array<string, string> $editorContext The sets of the editor: `stylesSet`, `colorsSet`…
     *
     * @return array<string, mixed> The props to render the component with
     */
    public function propsFor(array $editorContext): array
    {
        $props = array_filter(
            ['title' => $this->title, 'icon' => $this->icon, 'label' => $this->label],
            static fn(?string $value): bool => null !== $value,
        );

        foreach ($this->context as $prop => $key) {
            $props[$prop] = $editorContext[$key] ?? throw new \InvalidArgumentException(
                \sprintf('The tool "%s" reads "%s" from the editor, which does not provide it.', $this->name, $key),
            );
        }

        return [...$props, ...$this->props];
    }
}
