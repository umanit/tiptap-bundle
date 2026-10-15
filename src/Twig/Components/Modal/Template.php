<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\DTO\TemplatesSet;
use Umanit\TiptapBundle\Provider\TemplatesSetProviderInterface;

#[AsTwigComponent(name: 'Umanit:Tiptap:Modal:Template', template: '@UmanitTiptap/components/Modal/Template.html.twig')]
final class Template
{
    public string $name = 'default';
    public string $id;

    public function __construct(
        private readonly TemplatesSetProviderInterface $templatesSetProvider,
    ) {
    }

    public function getTemplates(): TemplatesSet
    {
        return $this->templatesSetProvider->getTemplatesSet($this->name);
    }
}
