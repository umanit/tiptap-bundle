<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

use Symfony\Component\Process\Process;
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

    public function run(iterable $contents, int $workers = 4, ?string $legacyStylesFile = null): \Generator
    {
        if (!is_file($this->scriptPath)) {
            throw new \RuntimeException(\sprintf('The script of the editor cannot be found: %s', $this->scriptPath));
        }

        if (null !== $legacyStylesFile && !is_file($legacyStylesFile)) {
            throw new \RuntimeException(\sprintf('The legacy styles file cannot be found: %s', $legacyStylesFile));
        }

        $command = [...(preg_split('/\s+/', trim($this->nodeCommand)) ?: []), $this->scriptPath];

        if (null !== $legacyStylesFile) {
            $command[] = '--legacy-styles=' . $legacyStylesFile;
        }

        $this->assertProtocolVersion($command);

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
     * @param list<string> $command
     *
     * @throws \RuntimeException If the script does not speak the protocol of this version of the bundle
     */
    private function assertProtocolVersion(array $command): void
    {
        $process = new Process([...$command, '--protocol-version']);
        $process->setTimeout(60);
        $process->run();

        $version = trim($process->getOutput());

        if ('' === $version && !$process->isSuccessful()) {
            throw new \RuntimeException(
                \sprintf(
                    'The script of the editor cannot be started (code %s): %s',
                    $process->getExitCode() ?? '?',
                    trim($process->getErrorOutput()) ?: 'no error message',
                ),
            );
        }

        if (((string) RoundTripProtocol::VERSION) !== $version) {
            throw new \RuntimeException(
                \sprintf(
                    'The script of the editor speaks the protocol "%s" while the bundle expects the version %d: '
                    . 'the Composer and the JavaScript packages of the bundle must be updated together.',
                    '' === $version ? 'unknown' : $version,
                    RoundTripProtocol::VERSION,
                ),
            );
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
            foreach ($worker->poll() as $response) {
                if (!isset($pending[$response->ref])) {
                    throw new \RuntimeException('The editor answered for a content which was not sent.');
                }

                $results[] = $response->toResult($pending[$response->ref]);
                unset($pending[$response->ref]);
            }
        }

        return $results;
    }
}
