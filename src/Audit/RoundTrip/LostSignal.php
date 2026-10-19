<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

/**
 * A formatting feature less present once the content went through the editor (`style:color`, `tag:div`, `attr:a@id`…).
 */
final readonly class LostSignal
{
    public function __construct(
        public string $signal,
        public int $before,
        public int $after,
    ) {
    }
}
