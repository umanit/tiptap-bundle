<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Report;

use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;

/**
 * Aggregates the results of the audit: by source, then by loss category.
 *
 * A content counts once per category, whatever the number of signals of this category it loses.
 *
 * @phpstan-type CategoryReport array{
 *     key: string,
 *     label: string,
 *     status: string,
 *     values: int,
 *     signals: array<string, int>,
 *     examples: list<string>
 * }
 * @phpstan-type SourceReport array{
 *     group: string,
 *     total: int,
 *     with_loss: int,
 *     errors: int,
 *     text_changed: array{count: int, examples: list<string>},
 *     error_examples: list<string>,
 *     categories: list<CategoryReport>
 * }
 */
class AuditReport
{
    /**
     * @var array<string, array{
     *     group: string,
     *     total: int,
     *     with_loss: int,
     *     errors: int,
     *     text_changed: array{count: int, examples: array<string, string>},
     *     error_examples: array<string, string>,
     *     categories: array<string, array{
     *         key: string,
     *         label: string,
     *         status: string,
     *         values: int,
     *         signals: array<string, int>,
     *         examples: array<string, string>
     *     }>
     * }>
     */
    private array $sources = [];

    public function __construct(
        private readonly LossClassifier $classifier,
        private readonly int $maxExamples = 3,
    ) {
    }

    public function add(RoundTripResult $result): void
    {
        $name = $result->content->source;
        $reference = $result->content->describe();

        $this->sources[$name] ??= [
            'group'          => $result->content->group,
            'total'          => 0,
            'with_loss'      => 0,
            'errors'         => 0,
            'text_changed'   => ['count' => 0, 'examples' => []],
            'error_examples' => [],
            'categories'     => [],
        ];

        $source = &$this->sources[$name];
        ++$source['total'];

        if (null !== $result->error) {
            ++$source['errors'];
            $source['error_examples'] = $this->withExample(
                $source['error_examples'],
                $result->content->id,
                \sprintf('%s : %s', $reference, $result->error),
            );

            return;
        }

        if ($result->textChanged) {
            ++$source['text_changed']['count'];
            $source['text_changed']['examples'] = $this->withExample(
                $source['text_changed']['examples'],
                $result->content->id,
                $reference,
            );
        }

        if ([] === $result->lost) {
            return;
        }

        ++$source['with_loss'];

        $touched = [];

        foreach ($result->lost as $lost) {
            $category = $this->classifier->classify($lost->signal);
            $key = $category->key;

            $source['categories'][$key] ??= [
                'key'      => $key,
                'label'    => $category->label,
                'status'   => $category->status->value,
                'values'   => 0,
                'signals'  => [],
                'examples' => [],
            ];

            $signals = &$source['categories'][$key]['signals'];
            $signals[$lost->signal] = ($signals[$lost->signal] ?? 0) + 1;
            unset($signals);

            if (!isset($touched[$key])) {
                $touched[$key] = true;
                ++$source['categories'][$key]['values'];
                $source['categories'][$key]['examples'] = $this->withExample(
                    $source['categories'][$key]['examples'],
                    $result->content->id,
                    $reference,
                );
            }
        }
    }

    /**
     * @return array{
     *     totals: array{values: int, with_loss: int, text_changed: int, errors: int},
     *     sources: array<string, SourceReport>
     * }
     */
    public function toArray(): array
    {
        $sources = [];
        $totals = ['values' => 0, 'with_loss' => 0, 'text_changed' => 0, 'errors' => 0];

        \ksort($this->sources);

        foreach ($this->sources as $name => $source) {
            $categories = \array_values($source['categories']);

            \usort($categories, static fn(array $a, array $b): int => $b['values'] <=> $a['values']);

            foreach ($categories as &$category) {
                \arsort($category['signals']);
                $category['examples'] = \array_values($category['examples']);
            }

            unset($category);

            $sources[$name] = [
                ...$source,
                'text_changed'   => [
                    'count'    => $source['text_changed']['count'],
                    'examples' => \array_values($source['text_changed']['examples']),
                ],
                'error_examples' => \array_values($source['error_examples']),
                'categories'     => $categories,
            ];

            $totals['values'] += $source['total'];
            $totals['with_loss'] += $source['with_loss'];
            $totals['text_changed'] += $source['text_changed']['count'];
            $totals['errors'] += $source['errors'];
        }

        return ['totals' => $totals, 'sources' => $sources];
    }

    /**
     * Keeps at most one example per row: a text translated in 25 locales must not fill all the examples.
     *
     * @param array<string, string> $examples Examples already kept, by row identifier
     *
     * @return array<string, string>
     */
    private function withExample(array $examples, string $rowId, string $example): array
    {
        if (\count($examples) < $this->maxExamples && !isset($examples[$rowId])) {
            $examples[$rowId] = $example;
        }

        return $examples;
    }
}
