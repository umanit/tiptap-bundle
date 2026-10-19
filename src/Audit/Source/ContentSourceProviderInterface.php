<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Source;

/**
 * Declares the sources of contents to audit. Every application service which implements it is picked up.
 */
interface ContentSourceProviderInterface
{
    /**
     * @return iterable<ContentSourceInterface>
     */
    public function getSources(): iterable;
}
