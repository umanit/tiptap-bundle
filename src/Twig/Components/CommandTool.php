<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:CommandTool', template: '@UmanitTiptap/components/CommandTool.html.twig')]
final class CommandTool
{
    public string $title;
    public string $class = '';
    public string $command;
    public ?string $icon = null;

    /** @var string|array<string, mixed>|null */
    public string|array|null $args = null;
    public ?string $label = null;

    /** @var array<string, mixed>|null When the button shows as active at the cursor (see `Tool::$active`) */
    public ?array $active = null;
    public bool $hideLabel = true;
    public int $iconSize = 18;
    public string $iconClass = '';
}
