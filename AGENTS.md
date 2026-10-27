# AGENTS.md

Guidance for AI coding agents working on `umanit/tiptap-bundle`. See [README.md](README.md) for what the bundle does.

## Project

Public (MIT), standalone Symfony bundle: a TipTap editor (Twig components, Stimulus controllers, form type) and the
tooling to audit, clean and migrate contents written with another editor.

- `src/` PHP code (`Umanit\TiptapBundle`): `Builder`, `Provider`, `DTO` (editor configuration), `Form`, `Twig`,
  `Audit`, `Cleaning`, `Palette`, `Migration`, `Rewrite`, `Command`, `Doctrine`,
  `DependencyInjection` (the features of the bundle, loaded on their own conditions).
- `assets/` JavaScript package `@umanit/tiptap-bundle` (`src`, `tests`, `bin`, `dist/css`).
- `templates/`, `translations/` (en, fr), `icons/`, `config/`.
- `tests/` PHPUnit; `assets/tests/` Vitest.
- `docs/` user documentation; `playground/` Symfony app validating the install with Encore and AssetMapper.
- `CHANGELOG.md` follows Keep a Changelog: add an entry under `[Unreleased]` for any user-visible change.

## Rules

- **Standalone**: the bundle only depends on its own code, Symfony, Twig, PSR and `ext-dom` (Doctrine only inside
  `src/Doctrine`). `phparkitect.php` enforces it; express such guards as allow-lists.
- **Public repository**: nothing about any private codebase, product or customer in code, tests, docs, comments, commits
  or the changelog.
- **English everywhere**: code, comments, docs, commit messages (Conventional Commits, e.g. `feat(editor): …`).
- **Commit messages**: keep the subject within 80 characters; wrap the body lines (the GrumPHP hook rejects lines over
  100 characters).
- **Native PHP classes**: never `use` them, reference them with a leading backslash (`\Exception`,
  `\DateTimeImmutable`).
- Namespaces: PHP `Umanit\TiptapBundle`, Twig `Umanit:Tiptap:…`, translation domain `UmanitTiptapBundle`.
- Native CSS only (custom properties, nesting): no SCSS, no Bootstrap.
- Keep `docs/` and `README.md` in line with any change of behavior, option or command.

## Checks

After any change, run:

```shell
make full_static_analyze
```

It runs PHPStan, PHPCS, PHPArkitect, Twig CS Fixer, Rector (dry run), ESLint, PHPUnit and Vitest. Passing is the source
of truth; ignore IDE inspections. A single tool can be run with its own target (`make phpstan`, `make vitest`…).

## Environment

Nothing in the project requires Nix: the `Makefile` and the tools only need PHP, Composer, Node and Yarn on the `PATH`.

- PHP 8.4 (`.php-version`) with `ext-dom` and `ext-libxml`.
- Node and Yarn at the versions of `mise.toml` (`mise install`, or any other version manager; Yarn through Corepack).
- Setup: `make install` (`composer install`, then `yarn install` in `assets/`). `make help` lists the targets, and
  `make fix` applies the automatic fixes (PHPCBF, Twig CS Fixer, Rector, ESLint).
- CI (`.github/workflows/ci.yml`) runs the same checks, plus the playground build and a request on each of its pages.

A Nix flake (`flake.nix`, loaded by `.envrc` when Nix is available) is provided as a convenience for contributors who use
it: `direnv allow` or `nix develop`. Do not assume it is active, and do not make a command depend on it.
