<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Umanit\TiptapBundle\DTO\StylesSets;

interface StylesSetsBuilderInterface
{
    /**
     * Name under which the built value is registered and referenced (e.g. `toolbar="default"`).
     */
    public static function getDefaultIndexName(): string;

    public function build(): StylesSets;
}
