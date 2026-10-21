<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

use Dom\Element;

/**
 * Reads and writes the `style` attribute of an element as a map of declarations.
 */
final class StyleDeclarations
{
    /**
     * @return array<string, string> Value by property (lowercase), in the order of the attribute
     */
    public static function read(Element $element): array
    {
        return self::parse($element->getAttribute('style') ?? '');
    }

    /**
     * @return array<string, string>
     */
    public static function parse(string $style): array
    {
        $declarations = [];

        // A `;` inside parentheses (`url(data:image/png;base64,…)`) does not end the declaration
        foreach (\preg_split('/;(?![^(]*\))/', $style) ?: [] as $declaration) {
            if (!\str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = \explode(':', $declaration, 2);
            $property = \strtolower(\trim($property));
            $value = \trim($value);

            if ('' !== $property && '' !== $value) {
                $declarations[$property] = $value;
            }
        }

        return $declarations;
    }

    /**
     * Writes the declarations as CKEditor does (`color:red;margin:0;`), and removes the attribute when none is left.
     *
     * @param array<string, string> $declarations
     */
    public static function write(Element $element, array $declarations): void
    {
        if ([] === $declarations) {
            $element->removeAttribute('style');

            return;
        }

        $style = '';

        foreach ($declarations as $property => $value) {
            $style .= $property . ':' . $value . ';';
        }

        $element->setAttribute('style', $style);
    }
}
