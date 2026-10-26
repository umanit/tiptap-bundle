<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

/**
 * The phase of a long operation, as it is told to a progress callback with the number of items done and their total
 * (`null` when it is not known yet).
 *
 * @phpstan-type ProgressCallback \Closure(MigrationPhase, int<0, max>, int<0, max>|null): void
 */
enum MigrationPhase: string
{
    case Analysis = 'analysis';
    case Writing = 'writing';
    case Rollback = 'rollback';
}
