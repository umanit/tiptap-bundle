# Checking a migration in the browser: the migration diff

The [audit](migration-audit.md) tells what the TipTap schema would drop from your contents as a whole. The **migration
diff** does the same, field by field, when someone opens a content in the editor: it compares the HTML which was stored
with what the editor made of it, and shows the difference.

It is meant for the environments where you check a migration (acceptance, staging…), **not for production**: it is off
by default.

## What it shows

When the editor loads a content it rewrites, a banner appears above the toolbar:

- when something would be lost (a `<div>`, an inline color, an indentation…), it lists the lost signals (the same ones
  as the [round-trip CLI](round-trip-audit.md#reading-the-result));
- when the HTML is only rewritten (`<b>` becomes `<strong>`, an alignment becomes a class…), it says so.

Nothing is shown for a content the editor keeps as it is. The **Show the differences** button of the banner opens a
window with the original and the edited contents side by side, then the HTML diff, one tag per line.

The comparison is made when the field is loaded: it does not change what is stored, and it is not updated while editing.

## Turning it on

For every field of the application, from the bundle configuration. Turn it on for the environments you want:

```yaml
# config/packages/umanit_tiptap.yaml
umanit_tiptap:
    migration_diff: false

when@staging:
    umanit_tiptap:
        migration_diff: true
```

The `migration_diff` option of the form type overrides it for one field:

```php
$builder->add('body', TiptapType::class, ['migration_diff' => true]);
```

With the Twig component, use `<twig:Umanit:Tiptap:Editor :migrationDiff="true" … />`.
