# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial repository skeleton and static analysis tooling.
- Toolbar, styles, colors and templates builders/providers, with neutral default builders.
- `Umanit:Tiptap:*` Twig components and `TiptapType` form type.
- Stimulus controllers and TipTap editor JavaScript (`@umanit/tiptap-bundle` package), file manager adapter registry.
- Native CSS (editor UI, content and email stylesheets) and PurgeCSS safelist.
- `assets/bin/roundtrip.js`: CLI which replays contents through the editor schema and reports what it drops, to audit
  existing contents before migrating a field from another editor (see `docs/round-trip-audit.md`).
- `umanit:tiptap:audit` command: audits what the schema would drop from the contents of an application, by source and by
  category of loss. Applications declare their sources (`ContentSourceProviderInterface`) and may add rules
  (`LossRuleInterface`); see `docs/migration-audit.md`. Requires `symfony/process`; the
  `umanit_tiptap.audit.node_command` option tells how to start Node.
- Embedded Lucide icons (`umanit-tiptap:*` icon set) and `UmanitTiptapBundle` translations (en, fr).
- The source view lays out the HTML (one block per line, nested blocks indented). It is for reading only: the
  content is compacted again when leaving the source view and on submit.
- Migration diff: when the editor rewrites the content a field loads, a banner lists what would be lost and a window
  shows the original and the edited contents side by side, with the HTML diff. Off by default; turned on with the
  `umanit_tiptap.migration_diff` setting (per environment) or the `migration_diff` option of `TiptapType`; see
  `docs/migration-diff.md`.
- `umanit:tiptap:clean` command: removes the leftovers of Word and of other editors (fonts, default line heights and
  spacing, languages, table layout, non-breaking spaces used to indent, empty paragraphs…) from the contents of the
  declared sources before they are migrated. It simulates by default, never changes the text, saves the contents it
  rewrites (`umanit:tiptap:migrate --rollback` puts them back), and is a set of rules (`CleaningRuleInterface`, tag
  `umanit_tiptap.cleaning_rule`); see `docs/cleaning.md`. Requires `ext-dom`.
- Tables: the width of a column (in px, from the cell properties) is written as `<col width>` in a `<colgroup>` and read
  back; only pixel widths are read, any other one is reported by the audit. A table can be aligned left, centered or
  right (`ut-table-center`, `ut-table-right`).
- Tables: the horizontal alignment of a cell (`ut-text-left|center|right|justify` on the `td`/`th`, from the cell
  properties). A cell written by another editor with `style="text-align:…"` or `align="…"` gets that class, so the audit
  no longer reports it as lost. The cell form keeps the other classes of the cell.
- Tables: `<table align="center|right">` (CKEditor) becomes `ut-table-center` / `ut-table-right`, so the audit no longer
  reports it as lost, and the `table-layout` cleaning rule keeps that `align` instead of removing it (an alignment
  nobody would otherwise get back, since it was removed before the migration could convert it). The `align` of a cell
  is converted the same way.
- Legacy import mode: the `backgroundColor` map of `legacy-styles.json` also applies to the cells of a table
  (`<td style="background-color:#eee">` becomes `ut-bg-grey-light`), without overwriting a `ut-bg-*` class the cell
  already has; the audit no longer reports a mapped background as lost.
- The `table-layout` cleaning rule keeps the `border` of a table when it is a number of pixels, which the table
  properties support; any other value is still removed.

- `tiptap-email.css` is generated from the `ut-*` rules of `tiptap-content.css` (`yarn build:email-css`,
  `assets/bin/build-email-css.js`): custom properties resolved, `rem` in `px`, logical properties written as physical
  ones, nesting flattened, so that the CSS inliners of the emails can read it. A test fails when the file is out of
  date, so the two cannot drift apart.

### Fixed

- A new table width class or column width was not shown in the editor (the TipTap table view ignored updates), and the
  `ut-w-*` classes lost against the `width: 100%` that sites give to the tables of their contents (now `!important`).
- Removing the background color of a cell left a `ut-bg-` class.
- The table properties could not remove a value: a border of 0 and an emptied class (width, alignment) kept the old
  ones. A border of 0 is now written (`border="0"`) and `tiptap-content.css` draws no border on the cells then.
- `ut-mx-auto` (centered image) and `ut-table-striped` were missing from `tiptap-email.css`.
- The classes Word and the like leave on a table, a cell or an image (`MsoTableGrid`, `MsoNormal`, `SCX*`, `kix-*`) were
  kept, unlike on the paragraphs: they are dropped now, the other classes stay.
- The source view laid out a table with a `<colgroup>` on a single line.
