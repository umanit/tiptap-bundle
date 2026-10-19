<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Fixtures\Audit;

use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;

/**
 * A source which yields the given HTML contents, without any storage behind.
 */
final readonly class FakeContentSource implements ContentSourceInterface
{
    /**
     * @param list<string> $htmls
     */
    public function __construct(
        private string $name,
        private string $group = ContentSourceRegistry::DEFAULT_GROUP,
        private array $htmls = ['<p style="color:red">1</p>', '<p style="color:red">2</p>', '<p>3</p>'],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function read(): \Generator
    {
        foreach ($this->htmls as $index => $html) {
            yield new AuditedContent($this->name, $this->group, (string) ($index + 1), null, 'fr', $html);
        }
    }
}
