<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\DTO\StylesSets;
use Umanit\TiptapBundle\Provider\StylesSetProviderInterface;

#[AsTwigComponent(name: 'Umanit:Tiptap:Styles', template: '@UmanitTiptap/components/Styles.html.twig')]
final class Styles
{
    public string $title;
    public string $label;
    public string $name = 'default';

    public function __construct(
        private readonly StylesSetProviderInterface $stylesSetProvider,
    ) {
    }

    public function getStylesSet(): StylesSets
    {
        return $this->stylesSetProvider->getStylesSet($this->name);
    }
}
