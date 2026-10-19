<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;

/**
 * Runs the TipTap editor under Node (jsdom) through `assets/bin/roundtrip.js`: the schema of the editor only exists in
 * JavaScript, so this is the only way to know faithfully what it keeps.
 */
final readonly class NodeRoundTripRunner implements RoundTripRunnerInterface
{
    /**
     * @param string $scriptPath           Path of the `assets/bin/roundtrip.js` script
     * @param string $nodeCommand          Command which starts Node; `yarn node` when the dependencies are in Yarn PnP
     * @param int    $maxInFlightPerWorker Contents sent to a process without an answer before waiting for its results
     */
    public function __construct(
        private string $scriptPath,
        private string $nodeCommand = 'node',
        private int $maxInFlightPerWorker = 200,
    ) {
    }

    public function run(iterable $contents, int $workers = 4): \Generator
    {
        if (!is_file($this->scriptPath)) {
            throw new \RuntimeException(\sprintf('The script of the editor cannot be found: %s', $this->scriptPath));
        }

        $command = [...(preg_split('/\s+/', trim($this->nodeCommand)) ?: []), $this->scriptPath];
        /** @var list<NodeWorker> $pool */
        $pool = [];
        /** @var array<int, AuditedContent> $pending */
        $pending = [];

        try {
            for ($i = 0; $i < max(1, $workers); ++$i) {
                $pool[] = new NodeWorker($command);
            }

            $reference = 0;

            foreach ($contents as $content) {
                $worker = $this->leastBusy($pool);

                while ($worker->getInFlight() >= $this->maxInFlightPerWorker) {
                    $received = $this->collect($pool, $pending);

                    yield from $received;

                    if ([] === $received) {
                        usleep(2000);
                    }
                }

                $pending[$reference] = $content;
                $worker->send($reference++, $content->html);

                // Yields the results as they come rather than once the whole source is read
                if (0 === $reference % 50) {
                    yield from $this->collect($pool, $pending);
                }
            }

            foreach ($pool as $worker) {
                $worker->closeInput();
            }

            while ([] !== $pending) {
                $received = $this->collect($pool, $pending);

                yield from $received;

                if ([] === $received) {
                    usleep(2000);
                }
            }
        } finally {
            foreach ($pool as $worker) {
                $worker->stop();
            }
        }
    }

    /**
     * @param non-empty-list<NodeWorker> $pool
     */
    private function leastBusy(array $pool): NodeWorker
    {
        usort($pool, static fn(NodeWorker $a, NodeWorker $b): int => $a->getInFlight() <=> $b->getInFlight());

        return $pool[0];
    }

    /**
     * @param list<NodeWorker>           $pool
     * @param array<int, AuditedContent> $pending
     *
     * @return list<RoundTripResult>
     */
    private function collect(array $pool, array &$pending): array
    {
        $results = [];

        foreach ($pool as $worker) {
            foreach ($worker->poll() as $row) {
                $reference = $row['ref'] ?? null;

                if (!\is_int($reference) || !isset($pending[$reference])) {
                    throw new \RuntimeException('The editor answered for a content which was not sent.');
                }

                $results[] = $this->toResult($pending[$reference], $row);
                unset($pending[$reference]);
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toResult(AuditedContent $content, array $row): RoundTripResult
    {
        if (isset($row['error']) && \is_string($row['error'])) {
            return new RoundTripResult($content, null, false, [], $row['error']);
        }

        $lost = [];

        foreach (\is_array($row['lost'] ?? null) ? $row['lost'] : [] as $item) {
            if (
                \is_array($item)
                && \is_string($item['signal'] ?? null)
                && \is_int($item['before'] ?? null)
                && \is_int($item['after'] ?? null)
            ) {
                $lost[] = new LostSignal($item['signal'], $item['before'], $item['after']);
            }
        }

        return new RoundTripResult(
            $content,
            \is_string($row['after'] ?? null) ? $row['after'] : null,
            true === ($row['textChanged'] ?? false),
            $lost,
            null,
            $this->inlineStyles($row['inlineStyles'] ?? null),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function inlineStyles(mixed $styles): array
    {
        $result = [];

        foreach (\is_array($styles) ? $styles : [] as $property => $values) {
            if (\is_string($property) && \is_array($values)) {
                $result[$property] = array_values(array_filter($values, \is_string(...)));
            }
        }

        return $result;
    }
}
