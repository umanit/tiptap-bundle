<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

/**
 * A margin of a few pixels (`margin-left: 8px`) is the offset Word gives to the paragraphs of a cell or a list, not
 * an indentation anyone chose.
 */
final class SmallIndentRule implements CleaningRuleInterface
{
    /** In pixels: the first indentation a person would pick is 30px or 40px. */
    private const float MAX_PIXELS = 12.0;

    public function getName(): string
    {
        return 'small-indent';
    }

    public function getDescription(): string
    {
        return 'Removes a `margin-left` of 12px or less (the offset of Word, not an indentation)';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, '[style]') as $element) {
            $declarations = StyleDeclarations::read($element);
            $margin = $declarations['margin-left'] ?? null;

            if (null !== $margin && $this->isSmall($margin)) {
                unset($declarations['margin-left']);
                StyleDeclarations::write($element, $declarations);
                ++$changes;
            }
        }

        return $changes;
    }

    private function isSmall(string $value): bool
    {
        if (1 !== \preg_match('/^(\d+(?:\.\d+)?)(px|pt)$/i', \trim($value), $matches)) {
            return false;
        }

        $pixels = (float) $matches[1] * ('pt' === \strtolower($matches[2]) ? 4 / 3 : 1);

        return $pixels <= self::MAX_PIXELS;
    }
}
