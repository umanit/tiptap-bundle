<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Source;

/**
 * A content source which can also replace what it holds: the migration only rewrites the sources which implement it.
 */
interface WritableContentSourceInterface extends ContentSourceInterface
{
    /**
     * Replaces contents of one row (or document) of the source.
     *
     * A content is replaced only if it is still the one that was read: a content edited in the meantime must be left
     * alone. The contents of a row are given together so that a source which stores them in a single value (a JSON
     * document) can write it once.
     *
     * @param string                               $id      Identifier of the row ({@see AuditedContent::$id})
     * @param array<string, array{string, string}> $changes For each path ({@see AuditedContent::$path}; empty for a
     *                                                      single value): the content expected, its replacement
     *
     * @return list<string> The paths which were replaced
     */
    public function replace(string $id, array $changes): array;
}
