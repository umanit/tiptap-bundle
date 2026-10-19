<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Audit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;

#[CoversClass(ContentSourceRegistry::class)]
final class ContentSourceRegistryTest extends TestCase
{
    public function testSourcesOfAllProvidersAreCollected(): void
    {
        $registry = $this->registry(
            [new FakeContentSource('a.body'), new FakeContentSource('b.body', ContentSourceRegistry::DEFAULT_GROUP)],
            [new FakeContentSource('c.body', 'emailing')],
        );

        $sources = $registry->getSources();
        $names = array_map(static fn(ContentSourceInterface $source): string => $source->getName(), $sources);

        $this->assertSame(['a.body', 'b.body', 'c.body'], $names);
    }

    public function testSourcesCanBeSelectedByGroupAndByName(): void
    {
        $registry = $this->registry([new FakeContentSource('a.body'), new FakeContentSource('b.body', 'emailing')]);

        $this->assertSame(['b.body'], array_map(
            static fn(ContentSourceInterface $source): string => $source->getName(),
            $registry->getSources('emailing'),
        ));
        $this->assertSame(['a.body'], array_map(
            static fn(ContentSourceInterface $source): string => $source->getName(),
            $registry->getSources(null, ['a.body']),
        ));
    }

    public function testUnknownSourceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown content source "nope.nope". Known sources: a.body.');

        $this->registry([new FakeContentSource('a.body')])->getSources(null, ['nope.nope']);
    }

    public function testALaterProviderReplacesAnEarlierDeclaration(): void
    {
        $first = new FakeContentSource('a.body', 'content', ['first']);
        $later = new FakeContentSource('a.body', 'content', ['later']);

        $sources = $this->registry([$first], [$later])->getSources();

        $this->assertCount(1, $sources);
        $this->assertSame($later, $sources[0]);
    }

    /**
     * @param list<ContentSourceInterface> ...$sources One list of sources by provider
     */
    private function registry(array ...$sources): ContentSourceRegistry
    {
        return new ContentSourceRegistry(array_map(
            static fn(array $list): ContentSourceProviderInterface => new FakeContentSourceProvider($list),
            $sources,
        ));
    }
}
