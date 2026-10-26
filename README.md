# umanit/tiptap-bundle

Standalone [TipTap](https://tiptap.dev) rich text editor for Symfony: Twig components, Stimulus controllers and a form
type, plus the tooling to migrate existing contents written with another editor (CKEditor…). No dependency on Bootstrap
or on any application framework.

> **Status:** work in progress. Nothing is stable yet and the package is not released: see the
> [changelog](CHANGELOG.md).

## Requirements

- PHP `^8.4` with `ext-dom` and `ext-libxml` (a hard requirement: the cleaning relies on the HTML5 parser
  `\Dom\HTMLDocument`, which only exists since PHP 8.4)
- Symfony `^7.4 || ^8.0`, with `symfony/stimulus-bundle`, `symfony/ux-twig-component` and `symfony/ux-icons`
- Webpack Encore or AssetMapper
- Optional: `symfony/process` + Node (audit, clean and palette commands), `doctrine/dbal` and `doctrine/orm` (read and
  rewrite the columns of the database)

## Installation

```shell
composer require umanit/tiptap-bundle
```

The JavaScript package `@umanit/tiptap-bundle` ships in the `assets/` directory of the Composer package. Its four
Stimulus controllers (`editor`, `dropdown`, `image`, `modal`) are declared in `assets/controllers.json`, which Symfony
Flex does for you. With Webpack Encore, reference the package from your `package.json`:

```json
"@umanit/tiptap-bundle": "file:vendor/umanit/tiptap-bundle/assets"
```

With AssetMapper the bundle exposes its sources by itself, but `@tiptap/pm` has no root export, so its sub-paths must be
required explicitly. The [playground](playground/README.md) is a working application for both pipelines and lists the
exact steps.

## Usage

In a form:

```php
use Umanit\TiptapBundle\Form\Type\TiptapType;

$builder->add('body', TiptapType::class, [
    'toolbar'    => 'default',
    'styles_set' => 'default',
    'colors_set' => 'default',
]);
```

Other options: `background_colors_set`, `cell_background_colors_set`, `templates_set`, `file_manager_url`,
`file_manager_adapter` and `migration_diff`.

The `attr` of the widget land on the `<textarea>` which holds the content (the field a form submits), as with any field.
A `class` is added to the ones of the editor:

```
{{ form_widget(form.body, {attr: {'data-my-controller-target': 'input'}}) }}
```

Standalone, in Twig:

```
<twig:Umanit:Tiptap:Editor id="body" name="body" :content="html" />
```

### Customizing the editor

The tools, the toolbar, the styles, the colors and the templates are built by services you can replace. Implement one of
`ToolsBuilderInterface`, `ToolbarBuilderInterface`, `StylesSetsBuilderInterface`, `ColorsSetBuilderInterface`
or `TemplatesSetBuilderInterface` (`src/Builder`): it is picked up by autoconfiguration. The default builders are
neutral.

To add a tool to the toolbar, a TipTap extension or a manager of your own,
see [customization.md](docs/customization.md).

### Configuration

```yaml
# config/packages/umanit_tiptap.yaml
umanit_tiptap:
    migration_diff: false # warn when a loaded content is rewritten by the editor (acceptance environments)
    audit:
        node_command: node # how to start Node (`yarn node` with Yarn PnP)
```

### Styling

Native CSS, no preprocessor: `tiptap-editor.css` (the editor UI), `tiptap-content.css` (the content, to load where it is
displayed) and `tiptap-email.css` (inlined styles for emails), in `assets/dist/css`. The PurgeCSS safelist is exposed as
`@umanit/tiptap-bundle/purgecss-safelist`.

## Migrating existing contents

Four console commands take contents written with another editor to TipTap, in this order:

| Command                 | Purpose                                                    | Documentation                                 |
|-------------------------|------------------------------------------------------------|-----------------------------------------------|
| `umanit:tiptap:audit`   | Measure what the schema would drop from the contents       | [migration-audit.md](docs/migration-audit.md) |
| `umanit:tiptap:clean`   | Remove the leftovers of Word and of other editors          | [cleaning.md](docs/cleaning.md)               |
| `umanit:tiptap:palette` | Build the palette of the colors and sizes the contents use | [palette.md](docs/palette.md)                 |
| `umanit:tiptap:migrate` | Rewrite the contents (with backup and rollback)            | [migration.md](docs/migration.md)             |

All of them simulate by default. Other documents:

- [Customizing the editor](docs/customization.md): add a tool, a TipTap extension or a manager from your application.
- [The round-trip CLI](docs/round-trip-audit.md): the Node script behind the audit, usable on its own.
- [The migration diff](docs/migration-diff.md): in the editor, warn when a loaded content is rewritten and show the
  difference.

## Development

You need PHP 8.4 (`ext-dom`, `ext-libxml`), Composer, and Node and Yarn at the versions of `mise.toml`
(`mise install` works, so does any other version manager):

```shell
make install
make full_static_analyze
```

`make help` lists the targets; `make fix` applies the automatic fixes, `make playground` serves the demo application.

A Nix flake is provided for those who use Nix (`direnv allow` or `nix develop`); it is optional.

See [AGENTS.md](AGENTS.md) for the conventions of the project.

## License

[MIT](LICENSE)
