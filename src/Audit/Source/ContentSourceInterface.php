<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Source;

/**
 * A place where the application stores HTML contents written in another editor: a database column, a file…
 *
 * The bundle does not read the contents itself: it asks the application, which knows where they are.
 */
interface ContentSourceInterface
{
    /**
     * Unique name, used to select the source and to title its report (`article.body`).
     */
    public function getName(): string;

    /**
     * Lets the sources be audited by batches (a field that is migrated apart from the others).
     */
    public function getGroup(): string;

    /**
     * Reads the contents one by one, without loading the whole source in memory.
     *
     * @return \Generator<int, AuditedContent>
     */
    public function read(): \Generator;
}
