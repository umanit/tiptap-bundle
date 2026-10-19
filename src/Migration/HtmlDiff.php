<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

/**
 * Line diff of two HTML fragments, one tag per line so that a change of attribute shows up where it is.
 */
final readonly class HtmlDiff
{
    /** Beyond this number of cells in the comparison table, the fragments are shown whole, one after the other. */
    private const int MAX_CELLS = 4000000;

    /**
     * @param int<0, max> $context Unchanged lines kept around a change
     *
     * @return list<array{string, string}> Pairs of a marker (`-`, `+`, ` ` or `…` for the lines left out) and a line
     */
    public function diff(string $before, string $after, int $context = 2): array
    {
        $old = $this->split($before);
        $new = $this->split($after);

        if (\count($old) * \count($new) > self::MAX_CELLS) {
            return [...array_map(static fn(string $line): array => ['-', $line], $old),
                ...array_map(static fn(string $line): array => ['+', $line], $new)];
        }

        return $this->crop($this->compare($old, $new), $context);
    }

    /**
     * @return list<string>
     */
    private function split(string $html): array
    {
        $lines = explode("\n", (string) preg_replace('/>\s*</', ">\n<", trim($html)));

        return array_values(array_filter(array_map(trim(...), $lines), static fn(string $line): bool => '' !== $line));
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     *
     * @return list<array{string, string}>
     */
    private function compare(array $old, array $new): array
    {
        $oldCount = \count($old);
        $newCount = \count($new);

        // Length of the longest common run of lines, from the end of both
        $lengths = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($i = $oldCount - 1; $i >= 0; --$i) {
            for ($j = $newCount - 1; $j >= 0; --$j) {
                $lengths[$i][$j] = $old[$i] === $new[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;

        while ($i < $oldCount && $j < $newCount) {
            if ($old[$i] === $new[$j]) {
                $operations[] = [' ', $old[$i++]];
                ++$j;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $operations[] = ['-', $old[$i++]];
            } else {
                $operations[] = ['+', $new[$j++]];
            }
        }

        for (; $i < $oldCount; ++$i) {
            $operations[] = ['-', $old[$i]];
        }

        for (; $j < $newCount; ++$j) {
            $operations[] = ['+', $new[$j]];
        }

        return $operations;
    }

    /**
     * @param list<array{string, string}> $operations
     *
     * @return list<array{string, string}>
     */
    private function crop(array $operations, int $context): array
    {
        $keep = [];

        foreach ($operations as $index => [$marker]) {
            if (' ' === $marker) {
                continue;
            }

            for ($k = max(0, $index - $context); $k <= min(\count($operations) - 1, $index + $context); ++$k) {
                $keep[$k] = true;
            }
        }

        $lines = [];
        $skipping = false;

        foreach ($operations as $index => $operation) {
            if (isset($keep[$index])) {
                $lines[] = $operation;
                $skipping = false;
            } elseif (!$skipping) {
                $lines[] = ['…', ''];
                $skipping = true;
            }
        }

        return $lines;
    }
}
