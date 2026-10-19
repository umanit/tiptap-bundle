<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\RoundTrip;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;

final readonly class RoundTripResult
{
    /**
     * @param string|null      $after       HTML once it went through the editor (`null` if it could not be loaded)
     * @param bool             $textChanged The text itself (formatting aside) is no longer the same
     * @param list<LostSignal> $lost
     * @param string|null      $error       Message of the editor when it could not load the content
     *
     * @param array<string, list<string>> $inlineStyles Distinct normalized values the content sets inline, by property
     *                                                  (`color`, `backgroundColor`, `fontSize`)
     */
    public function __construct(
        public AuditedContent $content,
        public ?string $after,
        public bool $textChanged,
        public array $lost,
        public ?string $error = null,
        public array $inlineStyles = [],
    ) {
    }
}
