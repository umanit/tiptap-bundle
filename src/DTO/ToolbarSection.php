<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

final readonly class ToolbarSection
{
    public function __construct(
        /** @var list<Tool> */
        public array $tools,
    ) {
    }
}
