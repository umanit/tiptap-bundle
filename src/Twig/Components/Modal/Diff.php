<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Umanit:Tiptap:Modal:Diff', template: '@UmanitTiptap/components/Modal/Diff.html.twig')]
final class Diff
{
    public string $id;
}
