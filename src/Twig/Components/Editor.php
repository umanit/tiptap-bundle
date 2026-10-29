<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Umanit\TiptapBundle\Provider\ToolbarProviderInterface;

#[AsTwigComponent(name: 'Umanit:Tiptap:Editor', template: '@UmanitTiptap/components/Editor.html.twig')]
final class Editor
{
    public string $id;
    public ?string $name = null;
    public string $content = '';
    public string $toolbar = 'default';
    public string $stylesSet = 'default';
    public string $colorsSet = 'default';
    public string $backgroundColorsSet = 'default';
    public string $cellBackgroundColorsSet = 'default';
    public string $templatesSet = 'default';

    /** URL of the file manager opened by the image modal. The "browse" button is hidden when null. */
    public ?string $fileManagerUrl = null;

    /** Name of the file manager adapter registered on the JS side. */
    public ?string $fileManagerAdapter = null;

    /**
     * Attributes of the `<textarea>` which holds the content (`data-*`, `aria-*`, `class`, `rows`…), from the `attr` of
     * a form widget. A `class` is added to the ones of the editor; `id` and `name` are the ones of the editor.
     *
     * @var array<string, scalar|\Stringable|null>
     */
    public array $inputAttributes = [];

    /** Warns when the editor rewrites the content it loads, and shows the difference (to check a migration). */
    public bool $migrationDiff = false;

    /** Shows the editor greyed out and unusable; the content is not submitted (like any disabled form field). */
    public bool $disabled = false;

    public function __construct(
        private readonly ToolbarProviderInterface $toolbarProvider,
    ) {
    }

    public function hasFileManager(): bool
    {
        return null !== $this->fileManagerUrl && '' !== $this->fileManagerUrl;
    }

    public function hasTableTool(): bool
    {
        return $this->toolbarProvider->getToolbar($this->toolbar)->hasTableTool();
    }

    public function hasImageTool(): bool
    {
        return $this->toolbarProvider->getToolbar($this->toolbar)->hasImageTool();
    }

    public function hasLinkTool(): bool
    {
        return $this->toolbarProvider->getToolbar($this->toolbar)->hasLinkTool();
    }

    public function hasTemplateTool(): bool
    {
        return $this->toolbarProvider->getToolbar($this->toolbar)->hasTemplateTool();
    }

    public function hasAnchorTool(): bool
    {
        return $this->toolbarProvider->getToolbar($this->toolbar)->hasAnchorTool();
    }
}
