<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Migration;

enum MigrationPhase: string
{
    case Analysis = 'analysis';
    case Writing = 'writing';
    case Rollback = 'rollback';
}
