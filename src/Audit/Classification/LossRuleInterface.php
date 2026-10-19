<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Classification;

/**
 * Sorts a loss signal (`style:color`, `tag:div`, `attr:a@id`…) into a category.
 *
 * Applications add their own rules by implementing this interface: the first rule which knows a signal wins, and the
 * rules of the bundle come last (see {@see CkeditorLossRule}).
 */
interface LossRuleInterface
{
    /**
     * @return LossCategory|null `null` when the rule does not know the signal
     */
    public function categorize(string $signal): ?LossCategory;
}
