<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Doctrine;

use Umanit\TiptapBundle\Doctrine\ContentColumn;
use Umanit\TiptapBundle\Doctrine\ContentColumnProviderInterface;

/**
 * What an application does to have a column audited and migrated: declare it.
 */
final class ArticleColumnProvider implements ContentColumnProviderInterface
{
    public function getColumns(): iterable
    {
        yield new ContentColumn('article', 'body', labelColumn: 'title');
    }
}
