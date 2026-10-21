<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;

/**
 * Word numbers the items of a bulleted list (`<li value="17">`): a value which only has a meaning in an ordered list.
 */
final class ListValueRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'list-value';
    }

    public function getDescription(): string
    {
        return 'Removes the `value` attribute of the items of a bulleted list (it only counts in an ordered list)';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, 'ul > li[value]') as $item) {
            $item->removeAttribute('value');
            ++$changes;
        }

        return $changes;
    }
}
