<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;

/**
 * Word marks the language of every run of text (`<span lang="en-us" xml:lang="en-us">`): it has no visible effect.
 */
final class WordLanguageRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'word-language';
    }

    public function getDescription(): string
    {
        return 'Removes the `lang` and `xml:lang` attributes Word puts on each run of text (`<span>`)';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, 'span') as $span) {
            foreach (['lang', 'xml:lang'] as $attribute) {
                if ($span->hasAttribute($attribute)) {
                    $span->removeAttribute($attribute);
                    ++$changes;
                }
            }
        }

        return $changes;
    }
}
