<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:FontSize', template: '@UmanitTiptap/components/FontSize.html.twig')]
final class FontSize
{
    public string $title;
    public string $icon = 'umanit-tiptap:a-large-small';
    public int $iconSize = 18;

    /** @var list<int> */
    public array $sizes = [10, 12, 14, 18, 20, 22, 24, 28, 30, 32, 48];
}
