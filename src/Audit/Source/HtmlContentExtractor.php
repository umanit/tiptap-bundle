<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Source;

/**
 * Extracts from a stored value the contents which look like HTML.
 *
 * A column of rich text also holds plain texts (a title typed in the same field, an empty value): only the contents
 * which carry at least one tag are audited.
 */
final readonly class HtmlContentExtractor
{
    public function looksLikeHtml(string $content): bool
    {
        return 1 === preg_match('/<[a-z][a-z0-9-]*[\s\/>]/i', $content);
    }

    /**
     * @param mixed $value A string, or a structure decoded from JSON (a locale => content map, a nested document…)
     *                     which is walked at any depth
     *
     * @return \Generator<int, AuditedContent>
     */
    public function extract(
        string $source,
        string $group,
        string $id,
        ?string $label,
        mixed $value,
        string $path = '',
    ): \Generator {
        if (\is_string($value)) {
            if ($this->looksLikeHtml($value)) {
                yield new AuditedContent($source, $group, $id, $label, $path, $value);
            }

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $key => $child) {
                yield from $this->extract(
                    $source,
                    $group,
                    $id,
                    $label,
                    $child,
                    '' === $path ? (string) $key : $path . '.' . $key,
                );
            }
        }
    }
}
