<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

use Dom\HTMLDocument;

/**
 * Removes the leftovers of the editors the contents were pasted from (Word above all) before they are migrated.
 *
 * It works on the DOM, applies its rules one after the other and checks that the text of the content is the same
 * afterwards: a content for which that would not be true is left as it is. A content on which no rule finds anything
 * is returned untouched, byte for byte, so cleaning twice is the same as cleaning once.
 *
 * Its JavaScript twin is `assets/src/editor/html-cleaner.js`, which repairs what is pasted into the editor. They
 * overlap on the classes of Word (`WordClassesRule`, replayed on both sides from
 * `tests/Fixtures/Cleaning/word-classes-cases.json`) and must not drift there: the header of that file says what each
 * side does on its own.
 */
final readonly class HtmlCleaner
{
    /** Safety net: the rules settle in two or three rounds. */
    private const int MAX_ROUNDS = 5;

    /**
     * @param iterable<CleaningRuleInterface> $rules In the order they are applied
     */
    public function __construct(
        private iterable $rules,
    ) {
    }

    /**
     * @param list<string> $only
     * @param list<string> $skip
     */
    private static function isSelected(CleaningRuleInterface $rule, array $only, array $skip): bool
    {
        if (\in_array($rule->getName(), $skip, true)) {
            return false;
        }

        return [] === $only || \in_array($rule->getName(), $only, true);
    }

    /**
     * @return list<CleaningRuleInterface>
     */
    public function getRules(): array
    {
        $rules = [];

        foreach ($this->rules as $rule) {
            $rules[$rule->getName()] = $rule;
        }

        return \array_values($rules);
    }

    /**
     * @param list<string> $only Only applies these rules (all of them when empty)
     * @param list<string> $skip Does not apply these rules
     */
    public function clean(string $html, array $only = [], array $skip = []): CleaningResult
    {
        $document = HTMLDocument::createFromString('<body>' . $html, \LIBXML_NOERROR);
        $body = $document->body;

        if (null === $body) {
            return new CleaningResult($html, [], true);
        }

        $text = Html::flatten($body->textContent ?? '');
        $changes = [];

        $rules = \array_filter(
            $this->getRules(),
            static fn(CleaningRuleInterface $rule): bool => self::isSelected($rule, $only, $skip),
        );

        // One rule can give another something to do (a merged span whose color has become the default one):
        // go on until a whole round finds nothing, so that cleaning a cleaned content changes nothing
        for ($round = 0; $round < self::MAX_ROUNDS; ++$round) {
            $found = false;

            foreach ($rules as $rule) {
                $count = $rule->apply($body);

                if ($count > 0) {
                    $changes[$rule->getName()] = ($changes[$rule->getName()] ?? 0) + $count;
                    $found = true;
                }
            }

            if (!$found) {
                break;
            }
        }

        if ([] === $changes) {
            return new CleaningResult($html);
        }

        $body->normalize();

        if (Html::flatten($body->textContent ?? '') !== $text) {
            return new CleaningResult($html, [], true);
        }

        return new CleaningResult($body->innerHTML, $changes);
    }
}
