<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\DTO\Toolbar;

interface ToolbarProviderInterface
{
    public function getToolbar(string $name): Toolbar;
}
