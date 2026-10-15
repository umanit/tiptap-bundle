<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\DTO\Color;
use Umanit\TiptapBundle\Provider\ColorsSetProviderInterface;

#[AsTwigComponent(name: 'Umanit:Tiptap:Modal:Table', template: '@UmanitTiptap/components/Modal/Table.html.twig')]
final class Table
{
    public string $id;
    public string $cellBackgroundColorsSet = 'default';

    public function __construct(
        private readonly ColorsSetProviderInterface $colorsSetProvider,
    ) {
    }

    /**
     * @return iterable<Color>
     */
    public function cellBackgroundColors(): iterable
    {
        yield new Color('wysiwyg.modal.cell.bg_color.none', '');
        yield from $this->colorsSetProvider->getColorsSet($this->cellBackgroundColorsSet);
    }
}
