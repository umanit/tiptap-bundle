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
    private int $answers = 0;
    private bool $inputClosed = false;

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
        $this->input->write(RoundTripProtocol::encodeRequest($reference, $html) . "\n");
        ++$this->inFlight;
    }

    public function closeInput(): void
    {
        $this->inputClosed = true;
        $this->input->close();
    }

    /**
     * Complete results received since the last call.
     *
     * @return list<RoundTripResponse>
     *
     * @throws \RuntimeException If the process stopped leaving contents without an answer, or answered what is not
     *                           a response of the protocol
     */
    public function poll(): array
    {
        $results = $this->readLines();

        // `isRunning()` reads what is left in the pipes when the process ends: the output received between the read
        // above and that check must be consumed before concluding that some contents were left unanswered.
        if ($this->inFlight > 0 && !$this->process->isRunning()) {
            $results = [...$results, ...$this->readLines()];
        }

        if ($this->inFlight > 0 && !$this->process->isRunning()) {
            throw new \RuntimeException(
                \sprintf(
                    'The Node process stopped (code %s%s) before handling every content '
                    . '(%d without answer, %d bytes of unread output, input %s, %d answers received): %s',
                    $this->process->getExitCode() ?? '?',
                    $this->process->hasBeenSignaled() ? ', signal ' . $this->process->getTermSignal() : '',
                    $this->inFlight,
                    \strlen($this->buffer),
                    $this->inputClosed ? 'closed' : 'open',
                    $this->answers,
                    \trim($this->process->getErrorOutput()) ?: 'no error message',
                ),
            );
        }

        return $results;
    }

    public function stop(): void
    {
        $this->input->close();
        $this->process->stop(1);
    }

    /**
     * @return list<RoundTripResponse>
     */
    private function readLines(): array
    {
        $this->buffer .= $this->process->getIncrementalOutput();

        $lines = \explode("\n", $this->buffer);
        $this->buffer = \array_pop($lines);

        $results = [];

        foreach ($lines as $line) {
            if ('' === \trim($line)) {
                continue;
            }

            $results[] = RoundTripProtocol::decodeResponse($line);
            --$this->inFlight;
            ++$this->answers;
        }

        return $results;
    }
}
