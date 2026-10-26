<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

/**
 * The JSON lines spoken with `assets/bin/roundtrip.js`.
 *
 * A request is `{"ref": 3, "html": "<p>…</p>"}`. The response repeats its fields and adds either `error` (the editor
 * could not load the content), or `after` (the HTML once it went through the editor), `textChanged`, `lost` (a list of
 * `{"signal", "before", "after"}`) and `inlineStyles` (distinct values by property).
 *
 * {@see self::VERSION} is the one of `assets/src/audit/protocol.js`: the script tells it with `--protocol-version`.
 * Both are incremented together when a request or a response changes; `tests/Fixtures/RoundTrip/protocol-cases.json`
 * is replayed on each side to keep them alike.
 *
 * @internal
 */
final class RoundTripProtocol
{
    public const int VERSION = 1;

    public static function encodeRequest(int $reference, string $html): string
    {
        return \json_encode(['ref' => $reference, 'html' => $html], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws \UnexpectedValueException If the line is not a response of the protocol
     */
    public static function decodeResponse(string $line): RoundTripResponse
    {
        try {
            $row = \json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException(
                \sprintf('The editor answered a line which is not JSON (%s): %s', $exception->getMessage(), $line),
                previous: $exception,
            );
        }

        if (!\is_array($row) || !\is_int($row['ref'] ?? null)) {
            throw new \UnexpectedValueException(\sprintf('The editor answered a line without reference: %s', $line));
        }

        if (\array_key_exists('error', $row)) {
            if (!\is_string($row['error'])) {
                throw self::malformed('error', $line);
            }

            return new RoundTripResponse($row['ref'], $row['error'], null, false, [], []);
        }

        if (!\is_string($row['after'] ?? null)) {
            throw self::malformed('after', $line);
        }

        if (!\is_bool($row['textChanged'] ?? null)) {
            throw self::malformed('textChanged', $line);
        }

        return new RoundTripResponse(
            $row['ref'],
            null,
            $row['after'],
            $row['textChanged'],
            self::decodeLost($row['lost'] ?? null, $line),
            self::decodeInlineStyles($row['inlineStyles'] ?? null, $line),
        );
    }

    /**
     * @return list<LostSignal>
     */
    private static function decodeLost(mixed $items, string $line): array
    {
        if (!\is_array($items) || !\array_is_list($items)) {
            throw self::malformed('lost', $line);
        }

        $lost = [];

        foreach ($items as $item) {
            if (
                !\is_array($item)
                || !\is_string($item['signal'] ?? null)
                || !\is_int($item['before'] ?? null)
                || !\is_int($item['after'] ?? null)
            ) {
                throw self::malformed('lost', $line);
            }

            $lost[] = new LostSignal($item['signal'], $item['before'], $item['after']);
        }

        return $lost;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function decodeInlineStyles(mixed $styles, string $line): array
    {
        if (!\is_array($styles)) {
            throw self::malformed('inlineStyles', $line);
        }

        $decoded = [];

        foreach ($styles as $property => $values) {
            if (!\is_string($property) || !\is_array($values) || !\array_is_list($values)) {
                throw self::malformed('inlineStyles', $line);
            }

            foreach ($values as $value) {
                if (!\is_string($value)) {
                    throw self::malformed('inlineStyles', $line);
                }
            }

            $decoded[$property] = $values;
        }

        return $decoded;
    }

    private static function malformed(string $field, string $line): \UnexpectedValueException
    {
        return new \UnexpectedValueException(
            \sprintf('The editor answered a line whose "%s" is missing or malformed: %s', $field, $line),
        );
    }
}
