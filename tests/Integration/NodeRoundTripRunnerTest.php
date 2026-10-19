<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\RoundTrip\NodeRoundTripRunner;
use Umanit\TiptapBundle\Audit\RoundTrip\NodeWorker;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResult;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;

/**
 * Starts the real editor under Node: needs Node and the JavaScript dependencies of the bundle (`yarn install` in
 * `assets/`).
 */
#[CoversClass(NodeRoundTripRunner::class)]
#[CoversClass(NodeWorker::class)]
final class NodeRoundTripRunnerTest extends TestCase
{
    private const string SCRIPT = __DIR__ . '/../../assets/bin/roundtrip.js';

    public function testEveryContentComesBackWithItsOwnResult(): void
    {
        $htmls = [];

        foreach (range(0, 119) as $i) {
            $htmls[] = 0 === $i % 2 ? \sprintf('<p>ok %d</p>', $i) : \sprintf('<p style="color:red">red %d</p>', $i);
        }

        /** @var array<string, RoundTripResult> $results */
        $results = [];

        foreach ($this->runner()->run($this->contents($htmls), 3) as $result) {
            $results[$result->content->id] = $result;
        }

        $this->assertCount(120, $results);

        foreach ($results as $id => $result) {
            $signals = array_map(static fn($lost): string => $lost->signal, $result->lost);

            $this->assertSame($htmls[(int) $id], $result->content->html);
            $this->assertFalse($result->textChanged);
            $this->assertSame(0 === (int) $id % 2 ? [] : ['style:color'], $signals);
        }
    }

    public function testLegacyStylesConvertTheColorsOfThePalette(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'legacy');
        file_put_contents($file, '{"color": {"#ff0000": "ut-color-red-1"}}');

        try {
            $results = iterator_to_array(
                $this->runner()->run($this->contents(['<p><span style="color:#f00">a</span></p>']), 1, $file),
                false,
            );
        } finally {
            unlink($file);
        }

        $this->assertSame('<p><span class="ut-color-red-1">a</span></p>', $results[0]->after);
        $this->assertSame([], $results[0]->lost);
    }

    public function testMissingLegacyStylesFileIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('legacy styles file cannot be found');

        iterator_to_array($this->runner()->run($this->contents(['<p>x</p>']), 1, '/nope/legacy-styles.json'));
    }

    public function testMissingScriptIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be found');

        iterator_to_array(new NodeRoundTripRunner('/nope/roundtrip.js')->run($this->contents(['<p>x</p>'])));
    }

    public function testNodeFailureIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stopped');

        // `false` exits at once without answering
        iterator_to_array(new NodeRoundTripRunner(self::SCRIPT, 'false')->run($this->contents(['<p>x</p>']), 1));
    }

    private function runner(): NodeRoundTripRunner
    {
        if (!is_dir(__DIR__ . '/../../assets/node_modules/jsdom')) {
            self::markTestSkipped('The JavaScript dependencies are not installed.');
        }

        return new NodeRoundTripRunner(self::SCRIPT);
    }

    /**
     * @param list<string> $htmls
     *
     * @return \Generator<int, AuditedContent>
     */
    private function contents(array $htmls): \Generator
    {
        foreach ($htmls as $index => $html) {
            yield new AuditedContent('text.text', 'content', (string) $index, null, 'fr', $html);
        }
    }
}
