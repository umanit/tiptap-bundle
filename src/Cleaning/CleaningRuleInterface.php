<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Cleaning;

use Dom\HTMLElement;

/**
 * One kind of leftover removed from a content before it is migrated (see {@see HtmlCleaner}).
 *
 * A rule only removes what has no visible effect once the content is shown by the application: it must never remove
 * text, and must find nothing to do on a content it has already cleaned.
 */
interface CleaningRuleInterface
{
    /**
     * Unique name, used to select the rule (`--rule`, `--skip-rule`) and to title the report (`word-styles`).
     */
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Cleans the content in place.
     *
     * @return int<0, max> Number of changes made (attributes, declarations or elements removed…)
     */
    public function apply(HTMLElement $root): int;
}
