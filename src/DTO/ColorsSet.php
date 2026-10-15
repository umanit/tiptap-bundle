<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

/**
 * @implements \IteratorAggregate<int, Color>
 */
final readonly class ColorsSet implements \IteratorAggregate, \Countable
{
    public function __construct(
        /** @var list<Color> */
        public array $colors,
    ) {
    }

    /**
     * @return \ArrayIterator<int, Color>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->colors);
    }

    public function count(): int
    {
        return \count($this->colors);
    }
}
