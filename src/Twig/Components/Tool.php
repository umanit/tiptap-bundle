<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\DTO\Tool as ToolDefinition;

#[AsTwigComponent(name: 'Umanit:Tiptap:Tool', template: '@UmanitTiptap/components/Tool.html.twig')]
final class Tool
{
    public string $tool;

    /** Definition of a tool of the application: when it runs a command, it is rendered as a button. */
    public ?ToolDefinition $definition = null;

    public string $stylesSet = 'default';
    public string $colorsSet = 'default';
    public string $backgroundColorsSet = 'default';
}
