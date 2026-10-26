<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;

/**
 * One line answered by `assets/bin/roundtrip.js`, once {@see RoundTripProtocol::decodeResponse()} checked its shape.
 *
 * @internal
 */
final readonly class RoundTripResponse
{
    /**
     * @param int                         $ref          Reference of the content, as it was sent
     * @param string|null                 $error        Message of the editor when it could not load the content
     * @param string|null                 $after        HTML once it went through the editor (`null` with an error)
     * @param list<LostSignal>            $lost
     * @param array<string, list<string>> $inlineStyles Distinct normalized values the content sets inline, by property
     */
    public function __construct(
        public int $ref,
        public ?string $error,
        public ?string $after,
        public bool $textChanged,
        public array $lost,
        public array $inlineStyles,
    ) {
    }

    public function toResult(AuditedContent $content): RoundTripResult
    {
        return new RoundTripResult(
            $content,
            $this->after,
            $this->textChanged,
            $this->lost,
            $this->error,
            $this->inlineStyles,
        );
    }
}
