<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Source;

/**
 * An HTML content read from a {@see ContentSourceInterface}: a cell, or one locale/key of a multi-valued cell.
 */
final readonly class AuditedContent
{
    /**
     * @param string      $source Name of the source it comes from
     * @param string      $group  Group of the source it comes from
     * @param string      $path   Locale or path in a structured value (`fr`, `text.fr`); empty for a single value
     * @param string|null $label  Readable value which completes the identifier in the report (a reference, a key…)
     */
    public function __construct(
        public string $source,
        public string $group,
        public string $id,
        public ?string $label,
        public string $path,
        public string $html,
    ) {
    }

    /**
     * Readable reference to find the content again: `42 (login.title)/fr`.
     */
    public function describe(): string
    {
        return $this->id
            . (null !== $this->label && '' !== $this->label ? \sprintf(' (%s)', $this->label) : '')
            . ('' !== $this->path ? '/' . $this->path : '');
    }
}
