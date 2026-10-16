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

- [Auditing existing contents: the round-trip CLI](docs/round-trip-audit.md) — what a migration from another
  editor would drop, measured before migrating.

## License

[MIT](LICENSE)
