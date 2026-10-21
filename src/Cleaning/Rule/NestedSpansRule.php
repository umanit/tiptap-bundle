<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\Element;
use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

/**
 * CKEditor wraps the text in one `<span>` per style (`<span style="color"><span style="font-size">…`): once the
 * leftovers are gone, the spans without any attribute are removed and a span which only holds another one is merged
 * into it (the inner style wins, as it does in the browser).
 */
final class NestedSpansRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'nested-spans';
    }

    public function getDescription(): string
    {
        return 'Removes the `<span>` without attribute and merges the `<span>` which only hold another one';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        do {
            $pass = 0;

            foreach (Html::elements($root, 'span') as $span) {
                if (null === $span->parentNode) {
                    continue;
                }

                if ($span->hasAttribute('style') && [] === StyleDeclarations::read($span)) {
                    $span->removeAttribute('style');
                }

                if ([] === $span->getAttributeNames()) {
                    Html::unwrap($span);
                    ++$pass;
                } elseif (['style'] === $span->getAttributeNames() && $this->mergeIntoChild($span)) {
                    ++$pass;
                }
            }

            $changes += $pass;
        } while ($pass > 0);

        return $changes;
    }

    private function mergeIntoChild(Element $span): bool
    {
        $child = $span->firstChild;

        if (
            1 !== $span->childNodes->length
            || !$child instanceof Element
            || 'span' !== $child->localName
            || ['style'] !== $child->getAttributeNames()
        ) {
            return false;
        }

        StyleDeclarations::write($child, \array_merge(StyleDeclarations::read($span), StyleDeclarations::read($child)));
        Html::unwrap($span);

        return true;
    }
}
