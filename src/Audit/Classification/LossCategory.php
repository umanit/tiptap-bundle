<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Classification;

final readonly class LossCategory
{
    public function __construct(
        public string $key,
        public string $label,
        public LossStatus $status,
    ) {
    }
}
