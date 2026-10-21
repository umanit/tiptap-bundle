<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\Element;
use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

/**
 * Formatting around nothing (`<strong>&nbsp;</strong>`, `<span style="display:none">&nbsp;</span>`). The space it
 * holds is kept (it may separate two words), the element is not; a hidden one goes away with its space.
 */
final class EmptyInlineRule implements CleaningRuleInterface
{
    private const string SELECTOR = 'span, strong, b, em, i, u, s, sub, sup';

    public function getName(): string
    {
        return 'empty-inline';
    }

    public function getDescription(): string
    {
        return 'Removes the formatting (`<strong>`, `<span>`…) around spaces only, and the hidden `<span>` holding one';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        // From the innermost: an element emptied by this rule can make its parent empty too
        foreach (\array_reverse(Html::elements($root, self::SELECTOR)) as $element) {
            if (null === $element->parentNode || 0 < $element->childElementCount) {
                continue;
            }

            $text = $element->textContent ?? '';

            if ('' !== Html::flatten($text)) {
                continue;
            }

            if ('' === $text || $this->isHidden($element)) {
                $element->remove();
            } else {
                $element->replaceWith($text);
            }

            ++$changes;
        }

        return $changes;
    }

    private function isHidden(Element $element): bool
    {
        return 'none' === \strtolower(\str_replace(' ', '', StyleDeclarations::read($element)['display'] ?? ''));
    }
}
