<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:Source', template: '@UmanitTiptap/components/Source.html.twig')]
final class Source
{
    public string $title;
    public string $icon;
    public int $iconSize = 18;
}
