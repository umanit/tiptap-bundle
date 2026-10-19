<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Audit;

use Umanit\TiptapBundle\Audit\RoundTrip\LostSignal;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripRunnerInterface;

/**
 * Loses the color of every content (set inline: red, 14px) without starting Node and remembers what it was asked.
 */
final class FakeRoundTripRunner implements RoundTripRunnerInterface
{
    /** @var list<string> */
    public array $html = [];

    public int $workers = 0;

    public ?string $legacyStylesFile = null;

    public function __construct(
        private readonly ?\RuntimeException $failure = null,
    ) {
    }

    public function run(iterable $contents, int $workers = 4, ?string $legacyStylesFile = null): \Generator
    {
        $this->workers = $workers;
        $this->legacyStylesFile = $legacyStylesFile;

        if (null !== $this->failure) {
            throw $this->failure;
        }

        foreach ($contents as $content) {
            $this->html[] = $content->html;

            yield new RoundTripResult($content, '<p>x</p>', false, [new LostSignal('style:color', 1, 0)], null, [
                'color'    => ['#ff0000'],
                'fontSize' => ['14px'],
            ]);
        }
    }
}
