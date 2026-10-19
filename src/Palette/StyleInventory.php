<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Palette;

use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;

/**
 * How many contents set each inline color, background color and font size: what a palette is built from.
 *
 * A value is counted once by content, however many times the content repeats it.
 */
final class StyleInventory
{
    public const array PROPERTIES = ['color', 'backgroundColor', 'fontSize'];

    /** @var array<string, array<string, int>> */
    private array $counts = ['color' => [], 'backgroundColor' => [], 'fontSize' => []];

    private int $contents = 0;

    public function add(RoundTripResult $result): void
    {
        ++$this->contents;

        foreach (self::PROPERTIES as $property) {
            foreach (array_unique($result->inlineStyles[$property] ?? []) as $value) {
                $this->counts[$property][$value] = ($this->counts[$property][$value] ?? 0) + 1;
            }
        }
    }

    public function getContentCount(): int
    {
        return $this->contents;
    }

    /**
     * Values of a property with the number of contents which set them, the most used first.
     *
     * @return array<string, int>
     */
    public function get(string $property): array
    {
        $counts = $this->counts[$property] ?? [];

        // Ties are sorted by value, so that two runs give the same report
        uksort($counts, static fn(string|int $a, string|int $b): int => [$counts[$b], (string) $a]
            <=> [$counts[$a], (string) $b]);

        return $counts;
    }

    /**
     * @return array{
     *     contents: int,
     *     color: array<string, int>,
     *     backgroundColor: array<string, int>,
     *     fontSize: array<string, int>,
     * }
     */
    public function toArray(): array
    {
        return [
            'contents'        => $this->contents,
            'color'           => $this->get('color'),
            'backgroundColor' => $this->get('backgroundColor'),
            'fontSize'        => $this->get('fontSize'),
        ];
    }
}
