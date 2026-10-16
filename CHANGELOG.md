# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial repository skeleton and static analysis tooling.
- Toolbar, styles, colors and templates builders/providers, with neutral default builders.
- `Umanit:Tiptap:*` Twig components and `TiptapType` form type.
- Stimulus controllers and TipTap editor JavaScript (`@umanit/tiptap-bundle` package), file manager adapter registry.
- Native CSS (editor UI, content and email stylesheets) and PurgeCSS safelist.
- `assets/bin/roundtrip.js`: CLI which replays contents through the editor schema and reports what it drops, to audit
  existing contents before migrating a field from another editor (see `docs/round-trip-audit.md`).
- Embedded Lucide icons (`umanit-tiptap:*` icon set) and `UmanitTiptapBundle` translations (en, fr).
