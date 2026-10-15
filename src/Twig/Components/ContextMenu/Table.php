<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\ContextMenu;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'Umanit:Tiptap:ContextMenu:Table',
    template: '@UmanitTiptap/components/ContextMenu/Table.html.twig',
)]
final class Table
{
}
