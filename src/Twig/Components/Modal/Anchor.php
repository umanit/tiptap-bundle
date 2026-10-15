<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:Modal:Anchor', template: '@UmanitTiptap/components/Modal/Anchor.html.twig')]
final class Anchor
{
    public string $id;
}
