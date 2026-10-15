<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class Tool implements \Stringable
{
    public function __construct(
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
