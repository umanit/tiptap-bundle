<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class Toolbar
{
    public function __construct(
        /** @var list<ToolbarSection> */
        public array $sections,
    ) {
    }

    public function hasTableTool(): bool
    {
        return $this->hasTool('table');
    }

    public function hasImageTool(): bool
    {
        return $this->hasTool('image');
    }

    public function hasLinkTool(): bool
    {
        return $this->hasTool('link');
    }

    public function hasTemplateTool(): bool
    {
        return $this->hasTool('template');
    }

    public function hasAnchorTool(): bool
    {
        return $this->hasTool('anchor');
    }

    public function hasTool(string $name): bool
    {
        foreach ($this->sections as $section) {
            foreach ($section->tools as $tool) {
                if ($name === $tool->name) {
                    return true;
                }
            }
        }

        return false;
    }
}
