<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class Style
{
    public function __construct(
        public string $name,
        public string $class,
    ) {
    }
}
