<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class StyleSet
{
    public function __construct(
        public string $title,
        /** @var list<Style> */
        public array $styles,
    ) {
    }
}
