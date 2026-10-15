<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\DTO\StylesSets;

interface StylesSetProviderInterface
{
    public function getStylesSet(string $name): StylesSets;
}
