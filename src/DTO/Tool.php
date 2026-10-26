<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

/**
 * A tool of the toolbar.
 *
 * Giving only a name selects one of the tools of the bundle (`bold`, `link`, `table`…). Giving a command too defines
 * a tool of the application: a button which runs a TipTap command or a command of a manager registered on the
 * JavaScript side (see docs/customization.md). It takes precedence over a tool of the bundle of the same name.
 */
final readonly class Tool implements \Stringable
{
    /**
     * @param string|null                      $command TipTap command (`toggleHighlight`) or `<manager>.<method>`
     * @param string|null                      $title   Tooltip and accessible name, translated; required with a command
     * @param string|null                      $icon    Name of a UX icon (`app:highlighter`)
     * @param string|null                      $label   Text of the button, hidden visually when there is an icon
     * @param string|array<string, mixed>|null $args    Arguments given to the command
     */
    public function __construct(
        public string $name,
        public ?string $command = null,
        public ?string $title = null,
        public ?string $icon = null,
        public ?string $label = null,
        public string|array|null $args = null,
    ) {
        if ('' === $name) {
            throw new \InvalidArgumentException('The name of a tool cannot be empty.');
        }

        if (null !== $command && '' === (string) $title) {
            throw new \InvalidArgumentException(
                \sprintf('The tool "%s" runs a command: it needs a title, which names the button.', $name),
            );
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function isCustom(): bool
    {
        return null !== $this->command;
    }
}
