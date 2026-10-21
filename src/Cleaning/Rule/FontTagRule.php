<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\HTMLElement;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;
use Umanit\TiptapBundle\Cleaning\StyleDeclarations;

/**
 * The obsolete `<font face="…" color="…">` of old editors: the face is lost like any font family, the color becomes
 * a `color` style so that the palette can convert it.
 */
final class FontTagRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'font-tag';
    }

    public function getDescription(): string
    {
        return 'Replaces `<font>` by a `<span>` which only keeps its color (as a style)';
    }

    public function apply(HTMLElement $root): int
    {
        $document = $root->ownerDocument;
        $changes = 0;

        if (null === $document) {
            return $changes;
        }

        foreach (Html::elements($root, 'font') as $font) {
            $color = \trim($font->getAttribute('color') ?? '');

            if ('' === $color) {
                Html::unwrap($font);
            } else {
                $span = $document->createElement('span');
                StyleDeclarations::write($span, ['color' => $color]);

                while (null !== $font->firstChild) {
                    $span->appendChild($font->firstChild);
                }

                $font->replaceWith($span);
            }

            ++$changes;
        }

        return $changes;
    }
}
