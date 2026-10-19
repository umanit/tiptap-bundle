<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Audit;

use Umanit\TiptapBundle\Audit\Classification\LossCategory;
use Umanit\TiptapBundle\Audit\Classification\LossRuleInterface;
use Umanit\TiptapBundle\Audit\Classification\LossStatus;

/**
 * What an application adds: its own rule, which must win over the one of the bundle.
 */
final class BrandLossRule implements LossRuleInterface
{
    public function categorize(string $signal): ?LossCategory
    {
        return 'style:color' === $signal ? new LossCategory('brand', 'Brand color', LossStatus::Convertible) : null;
    }
}
