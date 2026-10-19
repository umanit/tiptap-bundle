<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Audit;

use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;

/**
 * What an application declares: one source, picked up thanks to the autoconfiguration.
 */
final readonly class FixtureContentSourceProvider implements ContentSourceProviderInterface
{
    public function getSources(): iterable
    {
        yield new FakeContentSource('fixture.body', htmls: ['<p style="color:red">Red</p>', '<p>Plain</p>']);
    }
}
