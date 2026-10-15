<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\DTO\Toolbar as ToolbarDTO;
use Umanit\TiptapBundle\Provider\ToolbarProviderInterface;

#[AsTwigComponent(name: 'Umanit:Tiptap:Toolbar', template: '@UmanitTiptap/components/Toolbar.html.twig')]
final class Toolbar
{
    public string $name = 'default';
    public string $stylesSet = 'default';
    public string $colorsSet = 'default';
    public string $backgroundColorsSet = 'default';

    public function __construct(
        private readonly ToolbarProviderInterface $toolbarProvider,
    ) {
    }

    public function getToolbar(): ToolbarDTO
    {
        return $this->toolbarProvider->getToolbar($this->name);
    }
}
