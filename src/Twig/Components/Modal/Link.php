<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:Modal:Link', template: '@UmanitTiptap/components/Modal/Link.html.twig')]
final class Link
{
    public string $id;
}
