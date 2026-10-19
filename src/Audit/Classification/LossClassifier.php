<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Classification;

class LossClassifier
{
    public const string UNCLASSIFIED_KEY = 'unclassified';

    private readonly LossCategory $unclassified;

    /**
     * @param iterable<LossRuleInterface> $rules
     */
    public function __construct(
        private readonly iterable $rules,
    ) {
        $this->unclassified = new LossCategory(self::UNCLASSIFIED_KEY, 'Unclassified', LossStatus::Unclassified);
    }

    public function classify(string $signal): LossCategory
    {
        foreach ($this->rules as $rule) {
            $category = $rule->categorize($signal);

            if (null !== $category) {
                return $category;
            }
        }

        return $this->unclassified;
    }
}
