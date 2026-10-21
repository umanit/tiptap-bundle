<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning\Rule;

use Dom\Element;
use Dom\HTMLElement;
use Dom\Text;
use Umanit\TiptapBundle\Cleaning\CleaningRuleInterface;
use Umanit\TiptapBundle\Cleaning\Html;

/**
 * Non-breaking spaces used to indent or to space things out: a run of them at the start or the end of a block is
 * removed, and a run of at least two in the middle of a text becomes a single space. One non-breaking space on its own
 * (`10&nbsp;%`), or next to a space (`&nbsp; ` is how CKEditor keeps a double space), is left alone: that one is meant.
 */
final class NbspRunsRule implements CleaningRuleInterface
{
    private const string BLOCKS = 'p, li, h1, h2, h3, h4, h5, h6, td, th, blockquote, div';

    public function getName(): string
    {
        return 'nbsp-runs';
    }

    public function getDescription(): string
    {
        return 'Removes the non-breaking spaces at the start and the end of a block, and folds a run of them in a text';
    }

    public function apply(HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, self::BLOCKS) as $block) {
            // An empty paragraph made of a non-breaking space is the business of `empty-paragraphs`
            if (null !== $block->closest('pre') || '' === Html::flatten($block->textContent ?? '')) {
                continue;
            }

            $changes += $this->trimEdge($block, true) + $this->trimEdge($block, false);
        }

        foreach ($this->texts($root) as $text) {
            $folded = \preg_replace_callback(
                '/[ \x{A0}]{2,}/u',
                static fn(array $run): string => \substr_count($run[0], "\u{A0}") > 1 ? ' ' : $run[0],
                $text->data,
            );

            if (null !== $folded && $folded !== $text->data) {
                $text->data = $folded;
                ++$changes;
            }
        }

        return $changes;
    }

    /**
     * @return int<0, max>
     */
    private function trimEdge(Element $block, bool $start): int
    {
        $changes = 0;
        $leaves = Html::leaves($block);

        foreach ($start ? $leaves : \array_reverse($leaves) as $leaf) {
            if (!$leaf instanceof Text) {
                break;
            }

            $value = $leaf->data;

            // Layout whitespace between two tags: look past it
            if (1 === \preg_match('/^\s*$/', $value)) {
                continue;
            }

            $matched = \preg_match($start ? '/^[ \x{A0}]+/u' : '/[ \x{A0}]+$/u', $value, $run);

            if (1 !== $matched || !\str_contains($run[0], "\u{A0}")) {
                break;
            }

            ++$changes;
            $trimmed = \substr($value, $start ? \strlen($run[0]) : 0, \strlen($value) - \strlen($run[0]));

            if ('' === $trimmed) {
                $leaf->remove();

                continue;
            }

            $leaf->data = $trimmed;

            break;
        }

        return $changes;
    }

    /**
     * @return list<Text>
     */
    private function texts(Element $root): array
    {
        $texts = [];

        foreach ($root->childNodes as $child) {
            if ($child instanceof Text) {
                $texts[] = $child;
            } elseif (
                $child instanceof Element
                && !\in_array($child->localName, ['pre', 'code', 'textarea'], true)
            ) {
                \array_push($texts, ...$this->texts($child));
            }
        }

        return $texts;
    }
}
