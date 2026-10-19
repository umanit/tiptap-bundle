# Auditing the contents before migrating to TipTap

The bundle ships a console command which tells what the TipTap schema would drop from the contents you already have,
written in another editor (CKEditor…), **before** you migrate a field. It reads the contents from the places you
declare, runs them through the real editor (see [the round-trip CLI](round-trip-audit.md)) and reports, by source and by
category, what is lost. It writes nothing.

```bash
bin/console umanit:tiptap:audit
```

## Requirements

- `symfony/process`, which the command uses to start Node (`composer require --dev symfony/process`). Without it, the
  audit is not registered and the rest of the bundle is not affected.
- Node.js and `jsdom`: see the [requirements of the round-trip CLI](round-trip-audit.md#requirements).
- If the JavaScript dependencies of your application are managed by Yarn PnP, tell the bundle how to start Node:

  ```yaml
  # config/packages/umanit_tiptap.yaml
  umanit_tiptap:
      audit:
          node_command: 'yarn node' # default: 'node'
  ```

## Declaring where the contents are

The bundle does not know where your contents are stored: you declare **sources**. A source is a class which yields the
contents one by one; a provider returns the sources of the application. Any service implementing
`ContentSourceProviderInterface` is picked up by autoconfiguration.

```php
use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Audit\Source\ContentSourceInterface;
use Umanit\TiptapBundle\Audit\Source\ContentSourceProviderInterface;

final readonly class ArticleBodySource implements ContentSourceInterface
{
    public function __construct(
        private ArticleRepository $articles,
    ) {
    }

    public function getName(): string
    {
        return 'article.body'; // used to select the source and to title its report
    }

    public function getGroup(): string
    {
        return 'content'; // lets you audit sources by batches (--group)
    }

    public function read(): \Generator
    {
        // Stream the rows: never load the whole table in memory
        foreach ($this->articles->iterateAll() as $article) {
            yield new AuditedContent(
                $this->getName(),
                $this->getGroup(),
                (string) $article->getId(), // identifies the row in the report
                $article->getSlug(),        // optional readable label
                '',                         // locale or path when a row holds several contents
                $article->getBody(),
            );
        }
    }
}

final readonly class AppContentSourceProvider implements ContentSourceProviderInterface
{
    public function __construct(
        private ArticleRepository $articles,
    ) {
    }

    public function getSources(): iterable
    {
        yield new ArticleBodySource($this->articles);
    }
}
```

### Rows with several contents

When a cell holds one content by locale, or a structured document, give each content its own `path` (`fr`, `text.fr`…):
the report counts them separately but shows one example by row. `HtmlContentExtractor` does the walk for you: it takes a
string, or a structure decoded from JSON, and yields the `AuditedContent` of every value which looks like HTML (plain
texts are skipped).

```php
use Umanit\TiptapBundle\Audit\Source\HtmlContentExtractor;

yield from $this->extractor->extract(
    $this->getName(),
    $this->getGroup(),
    (string) $id,
    $label,
    json_decode($cell, true, 512, \JSON_THROW_ON_ERROR), // ['fr' => '<p>…</p>', 'en' => '<p>…</p>']
);
```

When two providers declare a source with the same name, the later one replaces the earlier one.

## Running it

```bash
bin/console umanit:tiptap:audit --list          # the declared sources
bin/console umanit:tiptap:audit                 # everything
bin/console umanit:tiptap:audit -s article.body # one source (repeatable)
bin/console umanit:tiptap:audit --group=content # one group
bin/console umanit:tiptap:audit --limit=500     # a quick try: 500 contents by source
bin/console umanit:tiptap:audit --workers=8 --examples=5
bin/console umanit:tiptap:audit --format=json > audit.json
```

| Option            | Meaning                                                                |
|-------------------|------------------------------------------------------------------------|
| `--list`          | Lists the sources and stops                                            |
| `-s`, `--source`  | Only audits this source; repeatable                                    |
| `-g`, `--group`   | Only audits the sources of this group                                  |
| `--format`        | `table` (default) or `json`; `json` writes the report only, to pipe it |
| `-w`, `--workers` | Node processes in parallel (default `4`)                               |
| `--examples`      | Examples (row ids) shown by category (default `3`)                     |
| `--limit`         | Contents read by source, for a quick try                               |

The command exits with `0` once the report is written, whatever it found: it is a measure, not a check. It fails when an
option is invalid, a source is unknown, or Node cannot be started.

### With a palette

Colors and font sizes set inline are lost by default. Once you have a palette for your contents (see
[palette.md](palette.md)), give its `legacy-styles.json` to the audit: what the palette covers is converted to classes
and no longer reported, what it does not cover remains a loss.

```bash
bin/console umanit:tiptap:audit --legacy-styles=var/palette/legacy-styles.json
```

## Reading the report

For each source: how many contents were audited, how many lose something, then one line by **category** of loss.

```
text.text — 6874 contents, 2542 with a loss (37 %)

 Status       Category                    Contents  Main signals                                    Examples
 convertible  Text or background color    1639      style:color (1631), style:background-color (80) 38/en, 40/da
 to decide    Indentation                 1039      style:margin-left (1039)                        40/da, 41/cs
```

A content counts **once per category**, however many signals of that category it loses. Examples are `id (label)/path`,
at most one by row.

The category tells what to do about it, through its **status**:

| Status              | Meaning                                                                                |
|---------------------|----------------------------------------------------------------------------------------|
| `convertible`       | A migration can convert it without loss: an inline style becomes a class of the editor |
| `no visible effect` | It can be dropped without consequence (language attributes, Word metadata…)            |
| `to decide`         | It changes the appearance: extend the schema, convert differently, or accept the loss  |
| `unclassified`      | No rule knows this signal yet: it is always shown, nothing is hidden                   |

Two counters deserve a look first: **contents whose text changes** (an actual loss of content, not of formatting) and
**errors** (contents the editor could not load).

### JSON

```jsonc
{
  "totals": { "values": 7707, "with_loss": 2544, "text_changed": 0, "errors": 0 },
  "sources": {
    "text.text": {
      "group": "content",
      "total": 6874,
      "with_loss": 2542,
      "errors": 0,
      "text_changed": { "count": 0, "examples": [] },
      "error_examples": [],
      "categories": [
        {
          "key": "color",
          "label": "Text or background color",
          "status": "convertible",
          "values": 1639,
          "signals": { "style:color": 1631, "style:background-color": 80 },
          "examples": ["38/en", "40/da"]
        }
      ]
    }
  }
}
```

## Sorting the losses your way

The bundle knows how CKEditor's HTML differs from the TipTap schema (`CkeditorLossRule`). Your application may have its
own conventions, for instance a color which is not "convertible" because it is part of your brand. Add a rule: any
service implementing `LossRuleInterface` is picked up, and **the first rule which knows a signal wins; yours come before
the one of the bundle**.

```php
use Umanit\TiptapBundle\Audit\Classification\LossCategory;
use Umanit\TiptapBundle\Audit\Classification\LossRuleInterface;
use Umanit\TiptapBundle\Audit\Classification\LossStatus;

final class BrandLossRule implements LossRuleInterface
{
    public function categorize(string $signal): ?LossCategory
    {
        return 'style:color' === $signal
            ? new LossCategory('brand', 'Brand colors', LossStatus::Decision)
            : null; // not ours: the next rule decides
    }
}
```

The signals (`style:color`, `tag:div`, `attr:a@id`, `class:…`, `mark:…`) are described in
[the round-trip CLI](round-trip-audit.md#reading-the-result).
