<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Umanit\TiptapBundle\DTO\ColorsSet;

#[AutoconfigureTag('umanit_tiptap.colors_set_builder')]
interface ColorsSetBuilderInterface
{
    /**
     * Name under which the built value is registered and referenced (e.g. `toolbar="default"`).
     */
    public static function getDefaultIndexName(): string;

    public function build(): ColorsSet;
}
