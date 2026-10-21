<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

use Dom\Element;
use Dom\Text;

/**
 * Small helpers shared by the cleaning rules.
 */
final class Html
{
    /** Elements without content, which a block can start or end with. */
    private const array VOID_ELEMENTS = [
        'br',
        'img',
        'hr',
        'input',
        'iframe',
        'video',
        'audio',
        'canvas',
        'svg',
        'embed',
        'object',
    ];

    /**
     * The elements below a root, in document order, as a list which stays valid while the tree is changed.
     *
     * @return list<Element>
     */
    public static function elements(Element $root, string $selector = '*'): array
    {
        return \iterator_to_array($root->querySelectorAll($selector), false);
    }

    /**
     * Replaces an element by its children.
     */
    public static function unwrap(Element $element): void
    {
        $parent = $element->parentNode;

        if (null === $parent) {
            return;
        }

        while (null !== $element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $element->remove();
    }

    /**
     * Whether an element shows nothing: no text, no image, no table… (line breaks do not count).
     */
    public static function isBlank(Element $element): bool
    {
        return '' === self::flatten($element->textContent ?? '')
            && [] === self::elements($element, 'img,hr,input,iframe,video,audio,canvas,svg,embed,object,table');
    }

    /**
     * The text of a content without any space (non-breaking ones included): what must not change when cleaning.
     */
    public static function flatten(string $text): string
    {
        return \preg_replace('/[\s\x{A0}]+/u', '', $text) ?? $text;
    }

    /**
     * The texts and the elements without content (line break, image…) of an element, in document order.
     *
     * @return list<Text|Element>
     */
    public static function leaves(Element $element): array
    {
        $leaves = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof Text) {
                $leaves[] = $child;
            } elseif ($child instanceof Element) {
                if (\in_array($child->localName, self::VOID_ELEMENTS, true)) {
                    $leaves[] = $child;
                } else {
                    \array_push($leaves, ...self::leaves($child));
                }
            }
        }

        return $leaves;
    }
}
