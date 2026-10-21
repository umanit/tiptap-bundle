<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\Element;
use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;

/**
 * Empty paragraphs (`<p>&nbsp;</p>`) used to space blocks out: the ones at the start and the end of the content go
 * away and a run of them is folded into one. A single one between two blocks is kept, it may be what separates them.
 */
final class EmptyParagraphsRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'empty-paragraphs';
    }

    public function getDescription(): string
    {
        return 'Removes the empty paragraphs at the start and the end of the content and folds a run of them into one';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;
        $previousWasEmpty = false;
        $children = [];

        foreach ($root->childNodes as $child) {
            if ($child instanceof Element) {
                $children[] = $child;
            }
        }

        $isEmpty = static fn(Element $element): bool => 'p' === $element->localName && Html::isBlank($element);

        // Leading and trailing ones
        foreach ([$children, \array_reverse($children)] as $edge) {
            foreach ($edge as $child) {
                if (null === $child->parentNode || !$isEmpty($child)) {
                    break;
                }

                $child->remove();
                ++$changes;
            }
        }

        // Runs: only the first of a run stays
        foreach ($children as $child) {
            if (null === $child->parentNode) {
                continue;
            }

            if ($isEmpty($child)) {
                if ($previousWasEmpty) {
                    $child->remove();
                    ++$changes;
                }

                $previousWasEmpty = true;
            } else {
                $previousWasEmpty = false;
            }
        }

        return $changes;
    }
}
