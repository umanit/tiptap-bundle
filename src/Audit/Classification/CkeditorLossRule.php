<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Audit\Classification;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Loss categories met when the HTML produced by CKEditor goes through the TipTap schema.
 *
 * Only what has no visible effect is "no visible effect". The rest changes the appearance: either a migration knows
 * how to convert it (colors, sizes), or it has to be decided.
 */
#[AsTaggedItem(priority: -100)]
class CkeditorLossRule implements LossRuleInterface
{
    /** @var list<array{string, LossCategory}>|null */
    private ?array $rules = null;

    public function categorize(string $signal): ?LossCategory
    {
        foreach ($this->getRules() as [$pattern, $category]) {
            if (1 === \preg_match($pattern, $signal)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return list<array{string, LossCategory}>
     */
    private function getRules(): array
    {
        if (null !== $this->rules) {
            return $this->rules;
        }

        $convertible = LossStatus::Convertible;
        $decision = LossStatus::Decision;

        return $this->rules = [
            [
                '/^style:(color|background-color)$/',
                new LossCategory('color', 'Text or background color', $convertible),
            ],
            ['/^style:font-size$/', new LossCategory('font_size', 'Font size', $convertible)],
            [
                '/^style:text-align$/',
                new LossCategory('alignment', 'Alignment not converted (cells, lists…)', $decision),
            ],
            [
                '/^style:(margin-left|padding-left|text-indent)$/',
                new LossCategory('indent', 'Indentation', $decision),
            ],
            [
                '/^(style:font-family|tag:font|attr:font@.+)$/',
                new LossCategory('font_family', 'Font family', $decision),
            ],
            [
                '/^(tag:(table|thead|tbody|tfoot|caption|colgroup|col)'
                . '|attr:(table|tr|td|th|col)@.+'
                . '|style:(border\*|padding\*|width|height|vertical-align))$/',
                new LossCategory('table', 'Tables (borders, padding, widths)', $decision),
            ],
            ['/^attr:a@(id|name)$/', new LossCategory('anchor', 'Anchors', $decision)],
            [
                '/^(tag:(div|center|section|article|header|footer|aside|nav)|class:.+)$/',
                new LossCategory('block', 'Blocks and classes not kept', $decision),
            ],
            [
                '/^(attr:[a-z0-9]+@(lang|xml:lang|data-.+)|style:mso-\*)$/',
                new LossCategory(
                    'metadata',
                    'Metadata without visible effect (language, Word…)',
                    LossStatus::Acceptable,
                ),
            ],
            [
                '/^style:(line-height|margin(-top|-right|-bottom)?|letter-spacing|word-spacing|text-transform'
                . '|white-space|font-style|font-weight|text-decoration|display|float|list-style-type)$/',
                new LossCategory('typography', 'Typography and spacing', $decision),
            ],
        ];
    }
}
