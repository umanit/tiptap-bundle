<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\Element;
use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

/**
 * The layout Word writes on the tables (fixed widths and heights, a border on every cell, paddings…): the structure
 * (rows, cells, merged cells) is kept, the look is the one of the application.
 */
final class TableLayoutRule implements CleaningRuleInterface
{
    private const array TABLE_ATTRIBUTES = [
        'border',
        'cellpadding',
        'cellspacing',
        'align',
        'width',
        'height',
        'bgcolor',
    ];
    private const array CELL_ATTRIBUTES = ['valign', 'width', 'height', 'bgcolor'];
    private const array STYLE_PROPERTIES = ['width', 'height', 'vertical-align', 'border-collapse', 'border-spacing'];

    public function getName(): string
    {
        return 'table-layout';
    }

    public function getDescription(): string
    {
        return 'Removes the widths, heights, borders, paddings and alignments Word writes on tables and cells';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, 'table, thead, tbody, tfoot, tr, td, th, colgroup, col') as $element) {
            $attributes = 'table' === $element->localName ? self::TABLE_ATTRIBUTES : self::CELL_ATTRIBUTES;

            foreach ($attributes as $attribute) {
                if ($element->hasAttribute($attribute) && !$this->isKeptAlignment($element, $attribute)) {
                    $element->removeAttribute($attribute);
                    ++$changes;
                }
            }

            $declarations = StyleDeclarations::read($element);
            $kept = \array_filter(
                $declarations,
                static fn(string $property): bool => !\in_array($property, self::STYLE_PROPERTIES, true)
                    && !\str_starts_with($property, 'border')
                    && !\str_starts_with($property, 'padding'),
                \ARRAY_FILTER_USE_KEY,
            );

            $removed = \array_diff_key($declarations, $kept);

            if ([] !== $removed) {
                $changes += \count($removed);
                StyleDeclarations::write($element, $kept);
            }
        }

        return $changes;
    }

    /**
     * The editor turns the alignment of a table (center, right) into a class: it is not part of the Word layout.
     */
    private function isKeptAlignment(Element $element, string $attribute): bool
    {
        return 'table' === $element->localName
            && 'align' === $attribute
            && \in_array(\strtolower($element->getAttribute('align') ?? ''), ['center', 'right'], true);
    }
}
