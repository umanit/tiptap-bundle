<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class Template
{
    public function __construct(
        public string $name,
        public string $content,
        public ?string $description = null,
    ) {
    }
}
