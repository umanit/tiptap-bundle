<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class Color
{
    public function __construct(
        public string $label,
        public string $name,
    ) {
    }

    public function getAsTextColorClass(): string
    {
        return 'ut-color-' . $this->name;
    }

    public function getAsBackgroundColorClass(): string
    {
        return 'ut-bg-' . $this->name;
    }
}
