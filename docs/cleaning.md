# Cleaning existing contents

Contents pasted from Word carry a lot that nobody wrote on purpose: fonts the site does not load, the default line
height and spacing, a language on every run of text, a border and a width on every table cell, non-breaking spaces to
indent, empty paragraphs to space blocks out. None of it shows, but all of it keeps
[`umanit:tiptap:migrate`](migration.md) from rewriting the content, because the editor cannot keep it.

`umanit:tiptap:clean` removes it, before the migration:

```bash
bin/console umanit:tiptap:clean --list-rules # what it knows how to remove
bin/console umanit:tiptap:clean # simulation: nothing is written
bin/console umanit:tiptap:clean --details --examples=5 # shows what it does to a few contents
bin/console umanit:tiptap:clean --force # writes
bin/console umanit:tiptap:migrate --rollback=clean-20261021105530-a1b2c3 --force # undoes it
```

Then run [`umanit:tiptap:audit`](migration-audit.md) again: what is left is what needs a decision, or the palette
([palette.md](palette.md)).

## Guarantees

- **The text never changes.** After the rules ran, the text of the content (spaces aside) is compared with the one it
  had: a content for which it differs is left as it is and reported as skipped.
- **Nothing to clean, nothing rewritten.** A content on which no rule finds anything is not even serialized again.
  Cleaning twice is cleaning once: the rules run again until a whole round finds nothing.
- **Every rewritten content is saved first**, in the table of the migration backup, under an identifier which starts
  with `clean-`. `umanit:tiptap:migrate --rollback=<identifier> --force` puts the original contents back, except the
  ones edited since. A content edited between its reading and its writing is left alone.
- **Colors and font sizes are left to the palette**, which converts them to classes.

It needs the same things as the migration: the sources of the application (see [migration.md](migration.md)) and, to
write, the backup table.

## Rules

| Rule               | What it removes                                                                                             |
|--------------------|-------------------------------------------------------------------------------------------------------------|
| `word-language`    | `lang` and `xml:lang` on `<span>`                                                                           |
| `word-classes`     | Classes `Mso*` and `WordSection*`; the `<div>` which only wrapped the document                              |
| `font-tag`         | `<font>`: replaced by a `<span>` which only keeps its color                                                 |
| `word-styles`      | `font-family`, `line-height`, `margin-bottom` of 11px, `mso-*`, the automatic black, the Word look of links |
| `table-layout`     | Widths, heights, borders, paddings and alignments of tables and cells (the structure is kept; a table keeps its `align="center"`/`right` and its `border` in pixels) |
| `small-indent`     | A `margin-left` of 12px or less: the offset Word gives to lists and cells, not an indentation               |
| `list-value`       | `value` on the items of a bulleted list                                                                     |
| `nested-spans`     | `<span>` without attribute; a `<span>` which only holds another one is merged into it                       |
| `empty-inline`     | `<strong>`, `<span>`… around spaces only (the space stays); hidden `<span>` holding one                     |
| `nbsp-runs`        | Non-breaking spaces at the start and the end of a block; a run of two or more in a text                     |
| `empty-paragraphs` | Empty paragraphs at the start and the end of the content; a run of them folded into one                     |

The rules are applied in this order. `--rule` keeps only the ones given, `--skip-rule` leaves some out; both can be
repeated.

Some of them are choices rather than facts, and can be skipped:

- `word-styles` drops every `font-family`: the site decides the font.
- `table-layout` drops the widths of the columns, those set in the editor (`<col width>`) included.
- `small-indent` treats up to 12px as noise; 30px or 40px, which somebody picked, stay.
- A black text color is only removed when nothing else colors the text around it.

## Adding a rule

Implement `CleaningRuleInterface` and tag the service `umanit_tiptap.cleaning_rule` (the higher the priority, the
earlier it runs; the bundle's own rules go from 100 to 90). A rule works on the DOM it is given, in place, and returns
the number of changes it made:

```php
final class StripTrackingAttributesRule implements CleaningRuleInterface
{
    public function getName(): string
    {
        return 'strip-tracking';
    }

    public function getDescription(): string
    {
        return 'Removes the `data-auth` attribute of links';
    }

    public function apply(\Dom\HTMLElement $root): int
    {
        $changes = 0;

        foreach (Html::elements($root, 'a[data-auth]') as $link) {
            $link->removeAttribute('data-auth');
            ++$changes;
        }

        return $changes;
    }
}
```

A rule must not remove text, and must find nothing to do on a content it has already cleaned.

## Tests

```bash
php vendor/bin/phpunit tests/Unit/Cleaning tests/Unit/Command/CleanCommandTest.php
```
