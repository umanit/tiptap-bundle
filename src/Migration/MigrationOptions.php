<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

final readonly class MigrationOptions
{
    /**
     * @param positive-int      $workers          Node processes in parallel
     * @param string|null       $legacyStylesFile JSON map of the legacy import mode (see `umanit:tiptap:palette`)
     * @param list<string>      $accepted         Losses accepted on top of the ones without visible effect: a category
     *                                            key (`indent`) or a status (`decision`, `unclassified`)
     * @param bool              $apply            `false` to simulate: nothing is written
     * @param positive-int|null $limit            Maximum number of contents read by source (quick try)
     * @param int<0, max>       $maxExamples      References given by outcome in the report
     */
    public function __construct(
        public int $workers = 4,
        public ?string $legacyStylesFile = null,
        public array $accepted = [],
        public bool $apply = false,
        public ?int $limit = null,
        public int $maxExamples = 3,
    ) {
    }
}
