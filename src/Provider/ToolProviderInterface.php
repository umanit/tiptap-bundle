<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\DTO\Tool;

interface ToolProviderInterface
{
    /**
     * @throws \InvalidArgumentException When no tool has this name
     */
    public function getTool(string $name): Tool;
}
