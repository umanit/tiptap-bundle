<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;

/**
 * Word styles its elements with classes of its own (`MsoTableGrid`, `MsoNormal`…) and wraps the document in a
 * `<div class="WordSection1">`: nothing in an application stylesheet targets them.
 */
final class WordClassesRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'word-classes';
    }

    public function getDescription(): string
    {
        return 'Removes the classes `Mso*` and `WordSection*`, and the `<div>` which only wrapped the document';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, '[class]') as $element) {
            $classes = \preg_split('/\s+/', \trim($element->getAttribute('class') ?? '')) ?: [];
            $kept = \array_values(
                \array_filter(
                    $classes,
                    static fn(string $class): bool => 1 !== \preg_match('/^(Mso|WordSection)/', $class),
                ),
            );

            if (\count($kept) === \count($classes)) {
                continue;
            }

            ++$changes;

            if ([] === $kept) {
                $element->removeAttribute('class');
            } else {
                $element->setAttribute('class', \implode(' ', $kept));
            }

            if ('div' === $element->localName && [] === $element->getAttributeNames()) {
                Html::unwrap($element);
            }
        }

        return $changes;
    }
}
