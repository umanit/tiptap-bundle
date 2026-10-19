<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Migration;

use Umanit\TiptapBundle\Audit\RoundTrip\LostSignal;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripRunnerInterface;

/**
 * Answers what it is told for each HTML, without starting Node, and remembers how it was called.
 */
final class ScriptedRoundTripRunner implements RoundTripRunnerInterface
{
    public ?string $legacyStylesFile = null;

    /**
     * @param array<string, array{string, list<string>, bool}> $results HTML read => HTML converted, signals lost,
     *                                                                  text changed
     */
    public function __construct(
        private readonly array $results,
    ) {
    }

    public function run(iterable $contents, int $workers = 4, ?string $legacyStylesFile = null): \Generator
    {
        $this->legacyStylesFile = $legacyStylesFile;

        foreach ($contents as $content) {
            [$after, $lost, $textChanged] = $this->results[$content->html];

            yield new RoundTripResult(
                $content,
                $after,
                $textChanged,
                array_map(static fn(string $signal): LostSignal => new LostSignal($signal, 1, 0), $lost),
            );
        }
    }
}
