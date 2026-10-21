<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\Element;
use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

/**
 * The styles Word attaches to every run of text and which an application does not render as such: fonts it does not
 * load, the default line height and spacing, the automatic (black) color, the look of a link.
 *
 * Colors and font sizes are not touched here: they are converted by the palette (`umanit:tiptap:palette`).
 */
final class WordStylesRule implements CleaningRuleInterface
{
    private const array BLACK = ['#000000', '#000', 'black', 'rgb(0,0,0)', 'rgb(0, 0, 0)'];

    /** Blue and underline Word gives to a link: the application styles its links itself. */
    private const string LINK_COLOR = '#0563c1';

    /** Space after a paragraph by default in Word (8pt). */
    private const array DEFAULT_SPACING = ['11px', '10.6667px', '8pt'];

    public function getName(): string
    {
        return 'word-styles';
    }

    public function getDescription(): string
    {
        return 'Removes `font-family`, `line-height`, the default spacing, `mso-*`, the automatic black and the Word'
            . ' look of links';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, '[style]') as $element) {
            $declarations = StyleDeclarations::read($element);
            $kept = $declarations;

            foreach ($declarations as $property => $value) {
                if ($this->isLeftover($element, $property, \strtolower($value))) {
                    unset($kept[$property]);
                    ++$changes;
                }
            }

            if ($kept !== $declarations || '' === \trim($element->getAttribute('style') ?? '')) {
                StyleDeclarations::write($element, $kept);
            }
        }

        return $changes;
    }

    private function isLeftover(Element $element, string $property, string $value): bool
    {
        return match (true) {
            'font-family' === $property, 'line-height' === $property, \str_starts_with($property, 'mso-') => true,
            'margin-bottom' === $property => \in_array($value, self::DEFAULT_SPACING, true),
            'color' === $property && 'a' === $element->localName => self::LINK_COLOR === $value
                || ($this->isBlack($value) && $this->inheritsNoColor($element)),
            'text-decoration' === $property => 'a' === $element->localName && 'underline' === $value,
            'color' === $property => $this->isBlack($value) && $this->inheritsNoColor($element),
            default => false,
        };
    }

    private function isBlack(string $value): bool
    {
        return \in_array($value, self::BLACK, true);
    }

    /**
     * Black is only the default if no ancestor sets another color it would override.
     */
    private function inheritsNoColor(Element $element): bool
    {
        for ($ancestor = $element->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            $color = StyleDeclarations::read($ancestor)['color'] ?? null;

            if (null !== $color && !$this->isBlack(\strtolower($color)) && self::LINK_COLOR !== \strtolower($color)) {
                return false;
            }
        }

        return true;
    }
}
