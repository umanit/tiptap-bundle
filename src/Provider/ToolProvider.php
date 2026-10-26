<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Provider;

use Umanit\TiptapBundle\Builder\ToolsBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;

final readonly class ToolProvider implements ToolProviderInterface
{
    /** @var array<string, Tool> */
    private array $tools;

    /**
     * @param iterable<ToolsBuilderInterface> $builders The first one which lists a name wins: the ones of the
     *                                                  application come before the ones of the bundle
     */
    public function __construct(
        iterable $builders,
    ) {
        $indexed = [];

        foreach ($builders as $builder) {
            foreach ($builder->build() as $tool) {
                $indexed[$tool->name] ??= $tool;
            }
        }

        $this->tools = $indexed;
    }

    public function getTool(string $name): Tool
    {
        return $this->tools[$name] ?? throw new \InvalidArgumentException(
            \sprintf(
                'The tool "%s" does not exist. Available tools: %s.',
                $name,
                implode(', ', array_keys($this->tools)),
            ),
        );
    }
}
