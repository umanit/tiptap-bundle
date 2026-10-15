<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\Builder\TemplatesSetBuilderInterface;
use Umanit\TiptapBundle\DTO\TemplatesSet;

final readonly class TemplatesSetProvider implements TemplatesSetProviderInterface
{
    /** @var array<string, TemplatesSetBuilderInterface> */
    private array $templatesSet;

    /**
     * @param iterable<TemplatesSetBuilderInterface> $templatesSet
     */
    public function __construct(
        iterable $templatesSet,
    ) {
        $indexed = [];

        foreach ($templatesSet as $builder) {
            $indexed[$builder::getDefaultIndexName()] = $builder;
        }

        $this->templatesSet = $indexed;
    }

    public function getTemplatesSet(string $name): TemplatesSet
    {
        $templatesSet = $this->templatesSet[$name] ?? throw new \InvalidArgumentException(
            \sprintf('The templates set "%s" does not exist.', $name),
        );

        return $templatesSet->build();
    }
}
