<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * A Node process (`assets/bin/roundtrip.js`) which is sent contents as JSON lines.
 *
 * @internal
 */
final class NodeWorker
{
    private readonly InputStream $input;
    private readonly Process $process;
    private string $buffer = '';
    private int $inFlight = 0;

    /**
     * @param list<string> $command
     */
    public function __construct(array $command)
    {
        $this->input = new InputStream();
        $this->process = new Process($command);
        $this->process->setInput($this->input);
        $this->process->setTimeout(null);
        $this->process->start();
    }

    public function getInFlight(): int
    {
        return $this->inFlight;
    }

    public function send(int $reference, string $html): void
    {
        $line = \json_encode(['ref' => $reference, 'html' => $html], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $this->input->write($line . "\n");
        ++$this->inFlight;
    }

    public function closeInput(): void
    {
        $this->input->close();
    }

    /**
     * Complete results received since the last call.
     *
     * @return list<array<string, mixed>>
     *
     * @throws \RuntimeException If the process stopped leaving contents without an answer
     */
    public function poll(): array
    {
        $this->buffer .= $this->process->getIncrementalOutput();

        $lines = \explode("\n", $this->buffer);
        $this->buffer = \array_pop($lines);

        $results = [];

        foreach ($lines as $line) {
            if ('' === \trim($line)) {
                continue;
            }

            /** @var array<string, mixed> $decoded */
            $decoded = \json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            $results[] = $decoded;
            --$this->inFlight;
        }

        if ($this->inFlight > 0 && !$this->process->isRunning()) {
            throw new \RuntimeException(\sprintf(
                'The Node process stopped (code %s) before handling every content: %s',
                $this->process->getExitCode() ?? '?',
                \trim($this->process->getErrorOutput()) ?: 'no error message',
            ));
        }

        return $results;
    }

    public function stop(): void
    {
        $this->input->close();
        $this->process->stop(1);
    }
}
