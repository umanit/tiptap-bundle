# umanit/tiptap-bundle

Standalone [TipTap](https://tiptap.dev) rich text editor for Symfony: Twig components, Stimulus controllers and a
form type. No dependency on Bootstrap or on any application framework.

> **Status:** work in progress. The bundle is being extracted from a private codebase; nothing is stable yet.

## Requirements

- PHP `^8.4`
- Symfony `^7.4 || ^8.0`
- Webpack Encore or AssetMapper

## Installation

Documentation will be written once the extraction is complete.

## Development

```shell
direnv allow            # or: nix develop
composer install
make full_static_analyze
```

## Documentation

- [Auditing the contents before migrating to TipTap](docs/migration-audit.md) — the `umanit:tiptap:audit` command:
  declare where your contents are, and see what the schema would drop from them.
- [The round-trip CLI](docs/round-trip-audit.md) — the Node script behind it, usable on its own.
- [The migration diff](docs/migration-diff.md) — in the editor, warn when a loaded content is rewritten and show the
  difference (for acceptance environments, off by default).

## License

[MIT](LICENSE)
