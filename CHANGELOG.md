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
