<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:Tool', template: '@UmanitTiptap/components/Tool.html.twig')]
final class Tool
{
    public string $tool;
    public string $stylesSet = 'default';
    public string $colorsSet = 'default';
    public string $backgroundColorsSet = 'default';
}
