# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The bundle carries the Doctrine migration which creates the table of the migration backup, and registers it in
  `doctrine_migrations.migrations_paths` itself when `doctrine/doctrine-migrations-bundle` is installed: run
  `doctrine:migrations:migrate`, no migration to generate. It skips itself if the table already exists.
- `TiptapType` honours the `disabled` option of Symfony forms: the editor is displayed greyed out and unusable (read-only
  content, inert toolbar and status bar, disabled `<textarea>` so nothing is submitted). The Twig component takes a
  `disabled` prop, and the Stimulus controller a `disabled` value that can be toggled at runtime.
- `UmanitTiptapBundle` translations for 33 more locales: bg, cs, da, de, el, es, fi, he, hr, hu, id, it, ja, ka, ko,
  lo, ms, nl_BE, nl_NL, pl, pt, pt_BR, ro, ru, sk, sl, sr, sv, th, tr, vi, zh, zh_TW.
- Tools are complete definitions: a `Tool` has a name, a title and a command (or a Twig component), and is listed
  by a builder of tools (`ToolsBuilderInterface`, autoconfigured; `ToolProviderInterface` to pick one by name).
  An application adds its own tools, or replaces one of the bundle by giving it the same name. See
  `docs/customization.md`.
- `TiptapType`: the `attr` of the widget (`form_widget(form.body, {attr: {...}})`, or the `attr` option) are rendered on
  the `<textarea>` which holds the content, instead of being ignored. A `class` is added to the ones of the editor,
  `rows` can be overridden, `id` and `name` stay the editor's own. The Twig component takes them as `inputAttributes`.
- The editor is exposed on the root element of its controller: `getTipTapEditor(element)` finds it from the root or
  from an element inside, and the `umanit-tiptap:ready` event (`READY_EVENT`) tells when it has been created. No need to
  ask the Stimulus application for the controller anymore.
- JavaScript registry: `registerExtension()`, `registerManager()` and `registerToolState()`, exported by the package,
  add a TipTap extension, a manager (`<name>.<method>` commands) and the active state of a tool to every editor.
  `TipTapEditor.hasCommand()` tells whether a command can be run.
- A tool says when it shows as active at the cursor (`Tool::$active`): a node or a mark (with attributes), an
  attribute, a color or size class, a block class, or a state of the editor.
- The color, the size and the style of the selection are highlighted in the dropdowns of the toolbar (their entries
  had no active state, as the code which should have set it never reached them). `active` joins the PurgeCSS safelist.
- Test of the commands: the editor must run every command of the tools of the bundle (read from a snapshot which
  PHPUnit keeps up to date) and of the templates.

- `umanit:tiptap:migrate --purge=<identifier>` and `--purge-older-than=<days>` delete what a migration saved, so
  that the backup table does not grow forever (simulation unless `--force`). `MigrationBackupInterface` gains `purge()`.
- Round-trip protocol: `bin/roundtrip.js --protocol-version` tells the version of the JSON lines it speaks, and the
  commands which start it (`audit`, `migrate`, `palette`) stop with an explicit message when it is not the one
  of the bundle. A response which does not have the expected shape is refused instead of being read leniently.
- `ContentColumn`: the `connection` argument names the Doctrine connection which holds the column (the default one
  otherwise). The name of the source is then prefixed with it (`reporting:article.body`).
- Template modal: a template is chosen, then inserted with a validation button instead of on click.
- Font size dropdown: the default size entry comes first, apart from the sizes. Opening a dropdown closes the others.
- Editor: a border around the whole editor, a status bar showing the path of the elements around the cursor and a handle
  to resize the editing area in height (drag, or arrow keys). The height defaults to `--umanit-tiptap-height` (500px).
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
- Lists: the `text-align` CKEditor writes on a `<li>` (`center`, `right`, `justify`, and `left` in a right-to-left
  context) becomes the `ut-text-*` class of the paragraphs of the item, including the one the editor creates for an
  inline text. A paragraph keeps its own alignment, and the items of a sub-list their own. The audit no longer reports
  it as lost; an unknown value (`text-align:foo`) still is.
- Tables: `<table align="center|right">` (CKEditor) becomes `ut-table-center` / `ut-table-right`, so the audit no longer
  reports it as lost, and the `table-layout` cleaning rule keeps that `align` instead of removing it (an alignment
  nobody would otherwise get back, since it was removed before the migration could convert it). The `align` of a cell
  is converted the same way.
- Legacy import mode: the `backgroundColor` map of `legacy-styles.json` also applies to the cells of a table
  (`<td style="background-color:#eee">` becomes `ut-bg-grey-light`), without overwriting a `ut-bg-*` class the cell
  already has; the audit no longer reports a mapped background as lost.
- Tables carry no `border`, `cellpadding` or `cellspacing`: the look of a table (borders, padding, spacing) is the one
  of the CSS of the site, and a project which needs more sets it itself. The attributes are dropped on load (the audit
  reports them in the `table` category) and removed by the `table-layout` cleaning rule.
- Legacy import mode: the width of the cells (`<td style="width:274px">`, `<td width="120">`) becomes the width of
  their column (`<col width>`): the most frequent value of the column, the largest on a tie, stray widths of 1px to 5px
  and cells with a `colspan` ignored. A `<col width>` of the source stays first; the audit no longer reports a
  converted width as lost. The `table-layout` cleaning rule keeps the widths in pixels of `td`/`th` so that they reach
  the migration.

- `tiptap-email.css` is generated from the `ut-*` rules of `tiptap-content.css` (`yarn build:email-css`,
  `assets/bin/build-email-css.js`): custom properties resolved, `rem` in `px`, logical properties written as physical
  ones, nesting flattened, so that the CSS inliners of the emails can read it. A test fails when the file is out of
  date, so the two cannot drift apart.
- The classes of a `span` (`text-primary`…) are kept, as well as the ones of a formatting element
  (`<strong class="text-secondary">` becomes a `<span>` around the `<strong>`): they can come from the stylesheet of
  the site, the source mode or a template. Each `span` of the source comes out as one `span`, nested or not; the
  palette classes (`ut-color-*`, `ut-bg-*`, `ut-fs-*`) are set from the toolbar in the same span (single `textSpan`
  mark, which replaces the three `textColor`, `textBgColor` and `textSize` marks; the commands keep their names, and
  `unsetTextBgColor` is new). Only the classes of Word and the like are dropped.
- The classes of a list item, a quote, a code block, an inline `code` and a horizontal rule are kept, like the ones of
  the paragraphs.
- A `<div>` which has a class is kept (`div` node, nested or not): a block of the site written in the source view, with
  no button in the toolbar. A `<div>` without a class, or with only the classes of Word (`WordSection*` is now one of
  them), is still dropped and keeps its content.
- Legacy import mode: the color, background and size set inline on a formatting element
  (`<strong style="font-size:10pt">`, `<em>`, `<u>`…) are converted like the ones of a span, and a CSS color name (`red`) is read as its hex value.
- A list whose items are all written without a paragraph (CKEditor) is rewritten with `ut-list-tight`, which cancels
  the margin of the paragraphs of the items; see `docs/migration.md`.

### Changed

- The toolbar builders pick their tools by name (`$tools->getTool('bold')`) instead of creating them: a name which no
  builder of tools lists is an error when the toolbar is built. `DefaultToolbarBuilder` takes a `ToolProviderInterface`.
- The tools of the bundle are listed in PHP (`DefaultToolsBuilder`) and rendered by `Toolbar.html.twig`, which
  replaces the `Umanit:Tiptap:Tool` component and its table of tools. A component tool (`styles`, `color`, `fontSize`,
  `source`) is named by its definition, and translates its title itself.
- The active state of the toolbar tools is declared by the tool (`Tool::$active`), not deduced from the name of the
  command: a tool without it has no active state.
- The modal callbacks are given to the constructor of the managers (third argument) instead of being set on them
  afterwards.

### Fixed

- Symfony Flex ignored the JavaScript package, for lack of the `symfony-ux` keyword in `composer.json`: it now adds the
  reference to the package in `package.json`, and the controllers in `assets/controllers.json`. The
  importmap of an AssetMapper application stays to be filled by hand (see the README).
- `symfony/console` is a declared requirement: the commands rely on it (`#[AsCommand(help: …)]` needs 7.3 or later),
  but it was only pulled in indirectly and could resolve to an older version.
- Changing the alignment of a paragraph or a heading which came from existing content replaced the class only in
  part: the previous `ut-text-*` class stayed next to the new one.
- Paste: the alignment given by a `<style>` block is applied whatever the attributes or the case of the tag (it was
  only read from a bare `<style>`).
- The audit counted as lost what changes nothing on screen: attributes at their default (`ol start="1"`, `colspan="1"`,
  `rowspan="1"`), `text-align` `start`/`left`, initial values (`font-weight`, `font-style`, `text-decoration`,
  `white-space`), CSS-wide keywords (`inherit`, `initial`, `unset`) which are the default, `font-size: medium`, `background-color: transparent`, the default `list-style-type` of a list level, a named anchor whose `id` moved to another element, and
  a `<div>` without attribute.
- The reset button of the background color palette removed the text color, not the background.
- A new table width class or column width was not shown in the editor (the TipTap table view ignored updates), and the
  `ut-w-*` classes lost against the `width: 100%` that sites give to the tables of their contents (now `!important`).
- Removing the background color of a cell left a `ut-bg-` class.
- The table properties could not remove a value: an emptied class (width, alignment) kept the old one.
- `ut-mx-auto` (centered image) and `ut-table-striped` were missing from `tiptap-email.css`.
- The classes Word and the like leave on a table, a cell or an image (`MsoTableGrid`, `MsoNormal`, `SCX*`, `kix-*`) were
  kept, unlike on the paragraphs: they are dropped now, the other classes stay.
- The source view laid out a table with a `<colgroup>` on a single line.
- Links got `target="_blank"` and `rel="noopener noreferrer nofollow"` by default, including the existing ones when the
  content was loaded, which is unwanted for contents used in emails: they only keep the target chosen in the link modal.
  A checkbox of the link modal adds `rel="noopener noreferrer nofollow"` when wanted.
