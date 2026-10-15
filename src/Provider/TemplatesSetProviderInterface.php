<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\DTO\TemplatesSet;

interface TemplatesSetProviderInterface
{
    public function getTemplatesSet(string $name): TemplatesSet;
}
