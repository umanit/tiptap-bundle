<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Audit\RoundTrip;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\RoundTrip\LostSignal;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripProtocol;
use Umanit\TiptapBundle\Audit\RoundTrip\RoundTripResponse;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;

/**
 * The cases are shared with `assets/tests/protocol.test.js`, which replays them through the real script: a change of
 * the protocol on one side only breaks the tests of the other.
 *
 * @phpstan-type SharedCase array{
 *     name: string,
 *     request: array{ref: int, html: string},
 *     response: array<string, mixed>,
 *     node: bool
 * }
 */
#[CoversClass(RoundTripProtocol::class)]
#[CoversClass(RoundTripResponse::class)]
final class RoundTripProtocolTest extends TestCase
{
    private const string CASES = __DIR__ . '/../../../Fixtures/RoundTrip/protocol-cases.json';

    /**
     * @return iterable<string, array{string, SharedCase}>
     */
    public static function provideCases(): iterable
    {
        foreach (self::load()['cases'] as $case) {
            yield $case['name'] => [$case['name'], $case];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMalformedLines(): iterable
    {
        yield 'not JSON' => ['{nope', 'not JSON'];
        yield 'not an object' => ['"text"', 'without reference'];
        yield 'no reference' => ['{"after":"<p>x</p>"}', 'without reference'];
        yield 'a reference which is not an integer' => ['{"ref":"1"}', 'without reference'];
        yield 'an error which is not a message' => ['{"ref":1,"error":null}', '"error"'];
        yield 'no after' => ['{"ref":1,"textChanged":false,"lost":[],"inlineStyles":{}}', '"after"'];
        yield 'no textChanged' => ['{"ref":1,"after":"","lost":[],"inlineStyles":{}}', '"textChanged"'];
        yield 'lost which is not a list' => [
            '{"ref":1,"after":"","textChanged":false,"lost":"x","inlineStyles":{}}',
            '"lost"',
        ];
        yield 'a lost signal without count' => [
            '{"ref":1,"after":"","textChanged":false,"lost":[{"signal":"tag:p"}],"inlineStyles":{}}',
            '"lost"',
        ];
        yield 'inlineStyles which is not a map' => [
            '{"ref":1,"after":"","textChanged":false,"lost":[],"inlineStyles":"x"}',
            '"inlineStyles"',
        ];
        yield 'a value of inlineStyles which is not a string' => [
            '{"ref":1,"after":"","textChanged":false,"lost":[],"inlineStyles":{"color":[1]}}',
            '"inlineStyles"',
        ];
    }

    /**
     * @return array{version: int, cases: list<SharedCase>}
     */
    private static function load(): array
    {
        /** @var array{version: int, cases: list<SharedCase>} $shared */
        $shared = json_decode((string) file_get_contents(self::CASES), true, 512, \JSON_THROW_ON_ERROR);

        return $shared;
    }

    public function testTheSharedCasesAreWrittenForThisVersionOfTheProtocol(): void
    {
        $this->assertSame(RoundTripProtocol::VERSION, $this->load()['version']);
    }

    /**
     * @param SharedCase $case
     */
    #[DataProvider('provideCases')]
    public function testARequestIsEncodedAsTheSharedCaseSays(string $name, array $case): void
    {
        $request = $case['request'];

        $this->assertSame(
            json_encode($request, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            RoundTripProtocol::encodeRequest($request['ref'], $request['html']),
            $name,
        );
    }

    /**
     * @param SharedCase $case
     */
    #[DataProvider('provideCases')]
    public function testAResponseIsDecodedAsTheSharedCaseSays(string $name, array $case): void
    {
        /** @var array{ref: int, error?: string, after?: string, textChanged?: bool, lost?: list<array{signal: string, before: int, after: int}>, inlineStyles?: array<string, list<string>>} $expected */
        $expected = $case['response'];
        $response = RoundTripProtocol::decodeResponse(
            json_encode($expected, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
        );

        $this->assertSame($expected['ref'], $response->ref, $name);
        $this->assertSame($expected['error'] ?? null, $response->error, $name);
        $this->assertSame($expected['after'] ?? null, $response->after, $name);
        $this->assertSame($expected['textChanged'] ?? false, $response->textChanged, $name);
        $this->assertEquals(
            array_map(
                static fn(array $lost): LostSignal => new LostSignal($lost['signal'], $lost['before'], $lost['after']),
                $expected['lost'] ?? [],
            ),
            $response->lost,
            $name,
        );
        $this->assertSame($expected['inlineStyles'] ?? [], $response->inlineStyles, $name);
    }

    public function testAResponseBecomesTheResultOfItsContent(): void
    {
        $content = new AuditedContent('text.text', 'content', '1', null, 'fr', '<p style="color:red">x</p>');
        $result = RoundTripProtocol::decodeResponse(
            '{"ref":0,"after":"<p>x</p>","textChanged":true,"lost":[{"signal":"style:color","before":1,"after":0}],'
            . '"inlineStyles":{"color":["#ff0000"]}}',
        )->toResult($content);

        $this->assertSame($content, $result->content);
        $this->assertSame('<p>x</p>', $result->after);
        $this->assertTrue($result->textChanged);
        $this->assertSame(
            ['style:color'],
            array_map(static fn(LostSignal $lost): string => $lost->signal, $result->lost),
        );
        $this->assertSame(['color' => ['#ff0000']], $result->inlineStyles);
        $this->assertNull($result->error);
    }

    #[DataProvider('provideMalformedLines')]
    public function testAMalformedResponseIsRefused(string $line, string $message): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        RoundTripProtocol::decodeResponse($line);
    }
}
