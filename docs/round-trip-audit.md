# Auditing existing contents: the round-trip CLI

Moving a field from another editor (CKEditor, TinyMCE…) to TipTap does not only change the widget: the stored HTML is
loaded into the TipTap schema, and **whatever the schema does not support is silently dropped** the next time the
content is saved. The round-trip CLI tells you what would be dropped, before you migrate anything.

It replays each content through the real editor (the same extensions as the browser, under jsdom) and compares the HTML
before and after.

It does not touch your data: it reads JSON lines on stdin and writes JSON lines on stdout.

## Requirements

- Node.js (developed and tested with Node 24).
- `jsdom`, to run the editor outside of a browser. It is an **optional peer dependency** of the package: only this CLI
  needs it, the editor in the browser does not. Add it to the development dependencies of your application:

  ```bash
  yarn add --dev jsdom
  ```

  Working on the bundle itself, `yarn install` in `assets/` is enough: `jsdom` is also one of its development
  dependencies.

## Usage

```bash
node assets/bin/roundtrip.js < contents.jsonl > results.jsonl
```

### Input

One JSON object per line. Only `html` is read; every other field is echoed back untouched, so you can carry whatever
identifies the content (a table, an id, a locale…).

```jsonl
{"id": 1, "html": "<p style=\"text-align:center\"><b>Hello</b></p>"}
{"id": 2, "html": "<p><span style=\"color:#f00;font-size:14px\">Red</span></p>"}
{"id": 3, "html": "<div class=\"box\"><ul><li>x</li></ul></div>"}
{"id": 4, "html": "<p style=\"margin-left:40px\">Indented</p>"}
```

Blank lines are skipped. A line which is not valid JSON aborts the run with exit code `1`.

### Output

One line per input line, in the same order, with the input fields plus:

| Field         | Type    | Meaning                                                                                            |
|---------------|---------|----------------------------------------------------------------------------------------------------|
| `after`       | string  | The HTML once it went through the editor                                                           |
| `textChanged` | boolean | The text itself (ignoring whitespace and formatting) is no longer the same                         |
| `lost`        | array   | What the editor dropped: `{ "signal": …, "before": n, "after": m }`                                |
| `error`       | string  | Only when the editor could not load the content; `after`, `textChanged` and `lost` are then absent |

For the input above:

```jsonl
{"id":1,…,"after":"<p class=\"ut-text-center\"><strong>Hello</strong></p>","textChanged":false,"lost":[]}
{"id":2,…,"after":"<p>Red</p>","textChanged":false,"lost":[{"signal":"style:color","before":1,"after":0},{"signal":"style:font-size","before":1,"after":0}]}
{"id":3,…,"after":"<ul><li><p>x</p></li></ul><p></p>","textChanged":false,"lost":[{"signal":"tag:div","before":1,"after":0},{"signal":"class:box","before":1,"after":0}]}
{"id":4,…,"after":"<p>Indented</p>","textChanged":false,"lost":[{"signal":"style:margin-left","before":1,"after":0}]}
```

The first content loses nothing: the alignment became a class and `<b>` became `<strong>`, which is what the editor
does. The others lose their color and size, their `<div>` wrapper, and their indentation.

## Reading the result

A *signal* is one formatting feature of the HTML. `lost` lists the signals which are **less present after** than before.

| Signal                   | Example                | What it counts                                                                                                                                                                  |
|--------------------------|------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `tag:<name>`             | `tag:div`, `tag:table` | Elements                                                                                                                                                                        |
| `attr:<tag>@<attribute>` | `attr:a@id`            | Attributes other than `class` and `style`                                                                                                                                       |
| `style:<property>`       | `style:color`          | Inline style properties; `border-*`, `padding-*` and `text-decoration-*` are grouped as `style:border*`, `style:padding*` and `style:text-decoration`; `mso-*` as `style:mso-*` |
| `class:<name>`           | `class:box`            | Each CSS class                                                                                                                                                                  |
| `mark:<name>`            | `mark:strong`          | Bold, italic, underline… in **characters covered**, not elements                                                                                                                |

Some differences are not losses and are not reported:

- The editor renders `<b>` as `<strong>`, `<i>` as `<em>` and `<strike>`/`<del>` as `<s>`.
- A utility class counts as the inline style it replaces: `ut-text-*` for `style:text-align`, `ut-color-*` for
  `style:color`, `ut-bg-*` for `style:background-color` and `ut-fs-*` for `style:font-size`. This is how the editor
  stores them: **a content which only differs by this does not lose anything**.
- Adjacent marks are merged (`<b>a</b><b>b</b>` becomes one `<strong>`): marks are measured in characters for this
  reason.
- A `<span>` is a carrier for a style or an attribute, never reported by itself.
- The editor appends an empty trailing `<p></p>` after a block such as a table or a list.

`textChanged` should stay `false`: it is the sign of an actual loss of content (a `<script>`, an element the schema
cannot represent and discards with its text). Look at those first.

## Auditing a whole database

The CLI knows nothing about your database: export the contents as JSON lines, run it, and aggregate. For instance, to
count what is lost the most:

```bash
jq -r '.lost[].signal' results.jsonl | sort | uniq -c | sort -rn
```

```
   1631 style:color
   1629 style:font-size
   1039 style:margin-left
    ⋮
```

To list the contents which changed their text:

```bash
jq -c 'select(.textChanged) | {id}' results.jsonl
```

A run takes about 20 ms per content on one core (7,700 contents in 37 s on four processes): on a large database, split
the file and run one process per chunk.

```bash
split -n l/4 --additional-suffix=.jsonl contents.jsonl part-
ls part-*.jsonl | xargs -P 4 -I{} sh -c 'node assets/bin/roundtrip.js < {} > {}.out'
cat part-*.out > results.jsonl
```

The order is only kept within a chunk: carry an `id` in your input fields to find a content again.

### From an application

The CLI only deals with HTML. In a Symfony application, the `umanit:tiptap:audit` command does the rest: it reads the
contents from the sources you declare, spreads them over several processes, runs this script and sorts what is lost by
category. See [Auditing the contents before migrating to TipTap](migration-audit.md).

## Using it from JavaScript

The CLI is a thin wrapper around two modules which can be imported without it, for instance in a test of your own
content. They need a DOM (`window` and `document` globals).

```js
import RoundTripper from '@umanit/tiptap-bundle/src/audit/round-trip.js';

const roundTripper = new RoundTripper(window);

const { after, textChanged, lost } = roundTripper.run('<p style="color:red">Hello</p>');
// after       → '<p>Hello</p>'
// textChanged → false
// lost        → [{ signal: 'style:color', before: 1, after: 0 }]

roundTripper.destroy();
```

`src/audit/signals.js` exports `collectSignals(html, parser)` and `lostSignals(before, after)` if you want the signals
without running the editor.

## Limits

- It measures the **schema**, not the rendering. A content which keeps its classes loses nothing here, but you still
  need the stylesheet which defines those classes (`ut-*`, your own) to look right.
- It uses the schema of the bundle as is: the colors, sizes, styles and templates you configure in PHP are options of
  the toolbar and do not change what the schema accepts.
- It cannot tell a deliberate omission from a defect: `style:font-family` is dropped by design, only you can say whether
  it matters for your contents.

## Tests

```bash
cd assets
node node_modules/vitest/vitest.mjs run tests/audit.test.js
```
