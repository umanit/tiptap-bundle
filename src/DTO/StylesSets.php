<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

/**
 * @implements \IteratorAggregate<int, StyleSet>
 */
final readonly class StylesSets implements \IteratorAggregate, \Countable
{
    public function __construct(
        /** @var list<StyleSet> */
        public array $sets,
    ) {
    }

    /**
     * @return \ArrayIterator<int, StyleSet>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->sets);
    }

    public function count(): int
    {
        return \count($this->sets);
    }
}
