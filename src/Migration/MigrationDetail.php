<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

/**
 * Why a content has not been migrated, with what the editor would have made of it.
 */
final readonly class MigrationDetail
{
    /**
     * @param list<string> $reasons What is lost (`style:img@width (image_size, decision): 1 → 0`), or the error
     * @param string|null  $after   HTML once it went through the editor (`null` if it could not be loaded)
     */
    public function __construct(
        public string $reference,
        public MigrationOutcome $outcome,
        public array $reasons,
        public string $before,
        public ?string $after,
    ) {
    }
}
