# Palette from existing contents

Contents written in another editor (CKEditor…) set their colors and font sizes inline (`style="color:#f00"`), while
the schema of the bundle only reads classes (`ut-color-*`, `ut-bg-*`, `ut-fs-*`). The `umanit:tiptap:palette` command
reads your contents, groups the colors and sizes they use and proposes the palette which replaces them.

It reads the same sources as [`umanit:tiptap:audit`](migration-audit.md) and needs `symfony/process` and Node in
the same way. Nothing is written unless you ask for it.

```bash
bin/console umanit:tiptap:palette                      # inventory and proposed palette
bin/console umanit:tiptap:palette --max-distance=32    # group the colors more loosely
bin/console umanit:tiptap:palette --format=json
bin/console umanit:tiptap:palette \
    --output-dir=var/palette --builder-class='App\Wysiwyg\LegacyColorsSetBuilder'
```

## Inventory

Each color, background color and font size set inline is counted once per content which uses it. The values are
normalized first: `#F00`, `#ff0000` and `rgb(255, 0, 0)` are one color, and `10.5pt` is `14px` (1pt = 4/3px).

## Grouping

The most used color takes every color which is at most `--max-distance` away from it (the largest difference on a
red, green or blue channel, 24 by default), then the most used of the remaining colors does the same. A group is
thus represented by a color of your contents, not by an average. The text colors and the background colors share the
same palette, as the `ColorsSet` does.

The sizes are rounded to a pixel (`ut-fs-<n>`) and can be grouped too with `--max-size-distance` (0 by default).

The colors get generic names, numbered by usage inside their family: `red-1`, `red-2`, `blue-1`, `grey-1`…
**Rename them before committing the files**: the classes are what ends up in the migrated contents.

What the palette cannot express is listed apart and is not converted: color names (`red`), translucent colors,
sizes in `em`, `%` or keywords.

## Generated files

With `--output-dir`:

| File                  | Content                                                                                   |
|-----------------------|-------------------------------------------------------------------------------------------|
| `<Class>.php`         | The `ColorsSetBuilderInterface` of your application (`--builder-class`, `--index-name`)   |
| `palette.css`         | `--ut-color-*` properties and the `ut-color-*`, `ut-bg-*` and `ut-fs-*` classes           |
| `legacy-styles.json`  | Every inline value found, mapped to its class: the map of the legacy import mode          |

## Using the palette in the audit

Give the map to the round trip and the colors and sizes it covers stop being reported as lost
(see [the legacy import mode](round-trip-audit.md#legacy-import-mode)):

```bash
node assets/bin/roundtrip.js --legacy-styles=var/palette/legacy-styles.json < contents.jsonl
```
