<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Umanit\TiptapBundle\DTO\Tool;

/**
 * Lists tools, which the builders of toolbars then pick by name (see `ToolProviderInterface`).
 *
 * An application implements it to add its own tools, or to replace one of the bundle by giving it the same name.
 */
interface ToolsBuilderInterface
{
    /**
     * @return iterable<Tool>
     */
    public function build(): iterable;
}
