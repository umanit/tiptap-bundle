<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Doctrine;

/**
 * Declares the columns of the database which hold HTML contents: the table, the column and how it stores them are
 * enough for the bundle to audit and migrate them, without writing a content source.
 *
 * A column declared again by a later provider replaces the earlier one.
 */
interface ContentColumnProviderInterface
{
    /**
     * @return iterable<ContentColumn>
     */
    public function getColumns(): iterable;
}
