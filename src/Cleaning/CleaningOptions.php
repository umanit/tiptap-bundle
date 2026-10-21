<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

final readonly class CleaningOptions
{
    /**
     * @param list<string>      $only        Only applies these rules (all of them when empty)
     * @param list<string>      $skip        Does not apply these rules
     * @param bool              $apply       `false` to simulate: nothing is written
     * @param positive-int|null $limit       Maximum number of contents read by source (quick try)
     * @param int<0, max>       $maxExamples References given by outcome in the report
     * @param bool              $details     Keeps what each example content looked like before and after
     */
    public function __construct(
        public array $only = [],
        public array $skip = [],
        public bool $apply = false,
        public ?int $limit = null,
        public int $maxExamples = 3,
        public bool $details = false,
    ) {
    }
}
