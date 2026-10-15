<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:Modal:Image', template: '@UmanitTiptap/components/Modal/Image.html.twig')]
final class Image
{
    public string $id;
    public bool $fileManagerEnabled = false;
}
