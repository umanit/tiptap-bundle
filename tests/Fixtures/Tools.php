<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures;

use Umanit\TiptapBundle\Builder\DefaultToolsBuilder;
use Umanit\TiptapBundle\Provider\ToolProvider;

final class Tools
{
    /**
     * The tools of the bundle, as the container provides them.
     */
    public static function provider(): ToolProvider
    {
        return new ToolProvider([new DefaultToolsBuilder()]);
    }
}
