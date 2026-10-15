<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\DTO\ColorsSet;

interface ColorsSetProviderInterface
{
    public function getColorsSet(string $name): ColorsSet;
}
