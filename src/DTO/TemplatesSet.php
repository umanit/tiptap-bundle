<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\DTO;

/**
 * @implements \IteratorAggregate<int, Template>
 */
final readonly class TemplatesSet implements \IteratorAggregate, \Countable
{
    public function __construct(
        /** @var list<Template> */
        public array $templates,
    ) {
    }

    /**
     * @return \ArrayIterator<int, Template>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->templates);
    }

    public function count(): int
    {
        return \count($this->templates);
    }
}
