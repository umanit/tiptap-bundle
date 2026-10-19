<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Audit;

use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;

final readonly class FakeContentSourceProvider implements ContentSourceProviderInterface
{
    /**
     * @param list<ContentSourceInterface> $sources
     */
    public function __construct(
        private array $sources = [],
    ) {
    }

    public function getSources(): iterable
    {
        return $this->sources;
    }
}
