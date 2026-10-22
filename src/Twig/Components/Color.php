<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\DTO\Color as DTOColor;
use Umanit\TiptapBundle\DTO\ColorsSet;
use Umanit\TiptapBundle\Provider\ColorsSetProviderInterface;

#[AsTwigComponent(name: 'Umanit:Tiptap:Color', template: '@UmanitTiptap/components/Color.html.twig')]
final class Color
{
    public ColorType $type;
    public string $name = 'default';
    public string $title = '';
    public string $icon = '';
    public int $iconSize = 18;

    public function __construct(
        private readonly ColorsSetProviderInterface $colorsSetProvider,
    ) {
    }

    public function colors(): ColorsSet
    {
        return $this->colorsSetProvider->getColorsSet($this->name);
    }

    public function command(): string
    {
        return match ($this->type) {
            ColorType::Text => 'setTextColor',
            ColorType::Background => 'setTextBgColor',
        };
    }

    public function resetCommand(): string
    {
        return match ($this->type) {
            ColorType::Text => 'unsetTextColor',
            ColorType::Background => 'unsetTextBgColor',
        };
    }

    public function btnLabel(): string
    {
        return match ($this->type) {
            ColorType::Text => 'A',
            ColorType::Background => '',
        };
    }

    public function getColorClass(DTOColor $color): string
    {
        return match ($this->type) {
            ColorType::Text => $color->getAsTextColorClass(),
            ColorType::Background => $color->getAsBackgroundColorClass(),
        };
    }
}
