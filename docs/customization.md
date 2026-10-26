# Customizing the editor from your application

Three kinds of customization, from the lightest to the deepest:

1. **Compose** what the bundle already provides: the toolbar, the styles, the colors and the templates are built by
   services you replace (see the [README](../README.md#customizing-the-editor)).
2. **Add a tool** to the toolbar: a button which runs a command.
3. **Add a behavior** to the editor: a TipTap extension, or a manager which opens a modal and talks to the document.

Adding a tool takes a PHP side (the tool) and a JavaScript side (what it runs).

## How the toolbar is made

- A **tool** (`Tool`) is a complete definition: a name, a title, and a command (or a Twig component).
- A **builder of tools** (`ToolsBuilderInterface`) lists tools. The bundle has one (`DefaultToolsBuilder`) for its own
  tools; yours is picked up by autoconfiguration. A tool with the name of another replaces it: the one of the
  application wins over the one of the bundle.
- A **builder of toolbars** (`ToolbarBuilderInterface`) picks tools by name, through `ToolProviderInterface`, and
  groups them in sections. A name which no builder lists is an error when the toolbar is built, in every environment.

```php
use Umanit\TiptapBundle\Builder\ToolbarBuilderInterface;
use Umanit\TiptapBundle\DTO\Toolbar;
use Umanit\TiptapBundle\DTO\ToolbarSection;
use Umanit\TiptapBundle\Provider\ToolProviderInterface;

final readonly class AppToolbarBuilder implements ToolbarBuilderInterface
{
    public function __construct(private ToolProviderInterface $tools)
    {
    }

    public static function getDefaultIndexName(): string
    {
        return 'article';
    }

    public function build(): Toolbar
    {
        return new Toolbar([
            new ToolbarSection(array_map($this->tools->getTool(...), ['bold', 'italic', 'link'])),
        ]);
    }
}
```

The names of the tools of the bundle are listed in `src/Builder/DefaultToolsBuilder.php`. To start from its toolbar,
build `new DefaultToolbarBuilder($tools)` and add your sections to `$toolbar->sections`.

## 1. A tool which runs a TipTap command

Take the `Highlight` extension of TipTap (`@tiptap/extension-highlight`).

Register the extension **before the first editor connects**: at the top level of the entrypoint of your application,
which imports the package.

```js
// assets/app.js
import Highlight from '@tiptap/extension-highlight';
import { registerExtension } from '@umanit/tiptap-bundle';

registerExtension(Highlight);
```

Then list the tool.

```php
use Umanit\TiptapBundle\Builder\ToolsBuilderInterface;
use Umanit\TiptapBundle\DTO\Tool;

final readonly class AppToolsBuilder implements ToolsBuilderInterface
{
    public function build(): iterable
    {
        yield new Tool(
            'highlight',
            'Highlight',
            command: 'toggleHighlight',
            icon: 'app:highlighter',
            active: ['name' => 'highlight'],
        );
    }
}
```

| Argument            | Role                                                                                              |
|---------------------|---------------------------------------------------------------------------------------------------|
| `name`              | Identifies the tool (`data-wysiwyg-tool`), and picks it in a toolbar.                            |
| `title`             | The tooltip and the accessible name of the button: a translation key, or a text.                 |
| `command`           | A TipTap command (`toggleHighlight`) or `<manager>.<method>` (below).                            |
| `icon`              | Name of a [UX icon](https://ux.symfony.com/icons) (`app:highlighter`).                           |
| `label`             | Text of the button: a translation key, or a text. Hidden visually when there is an icon.         |
| `args`              | The argument of the command: a string, or an array rendered as JSON (`['level' => 2]`).          |
| `active`            | When the button shows as active at the cursor (see below).                                       |
| `translationDomain` | The domain of the title and of the label (the one of the bundle by default).                     |

Add `highlight` to your toolbar builder, with `$this->tools->getTool('highlight')`.

## 2. A tool which opens a modal or needs more than a command: a manager

A manager is an object whose methods are the commands `<name>.<method>`. The bundle's own tools (`link.open`,
`table.openAddModal`…) are managers; yours are on the same footing.

```js
import { registerManager } from '@umanit/tiptap-bundle';

registerManager('callout', ({ editor, elements, modal }) => ({
  insert(args) {
    editor.chain().focus().insertContent(`<div class="callout">${args?.text ?? ''}</div>`).run();
  },
  destroy() {
    // Optional: called when the editor is destroyed
  },
}));
```

```php
new Tool('callout', 'Callout', command: 'callout.insert', args: ['text' => 'Note']);
```

The factory is called once per editor with:

| Key                  | Content                                                                                     |
|----------------------|---------------------------------------------------------------------------------------------|
| `editor`             | The TipTap `Editor`.                                                                        |
| `elements`           | The forms and groups of the editor which the bundle found (`linkForm`, `imageForm`…).       |
| `fileManagerAdapter` | The file manager adapter of the editor, or `null`.                                          |
| `modal`              | `{ open(element), close(element) }`: open or close the modal which contains an element.     |

A manager registered under the name of a manager of the bundle (`table`, `link`, `image`, `template`, `clipboard`,
`anchor`) replaces it. A name cannot contain a dot.

## 3. A tool which is more than a button: a component

A dropdown or a palette is a Twig component (`#[AsTwigComponent]`). The tool names it, and gives it its props.

```php
new Tool(
    'palette',
    'wysiwyg.color.choose',
    icon: 'app:palette',
    component: 'App:Palette',
    props: ['type' => 'text'],
    context: ['name' => 'colorsSet'],
);
```

The component receives the `title`, the `icon` and the `label` of the tool as props, then the `props`, then the ones
named in `context`, which are taken from the editor: `stylesSet`, `colorsSet` or `backgroundColorsSet`. The component
translates the title and the label itself. The tools of the bundle which are not plain buttons (`styles`, `color`,
`bgColor`, `fontSize`, `source`) are made this way.

## The active state of a tool

The button of a tool gets the class `active` when the tool says so, in `active`:

| `active`                                                 | The button is active when…                                    |
|----------------------------------------------------------|---------------------------------------------------------------|
| `['name' => 'bold']`                                     | the node or the mark is active at the cursor.                |
| `['name' => 'heading', 'attributes' => ['level' => 2]]`  | … with these attributes.                                      |
| `['attributes' => ['textAlign' => 'left']]`              | the attribute is set, whatever the node.                      |
| `['spanClass' => 'ut-color-red']`                        | the selection is in a span which has the class.               |
| `['blockClass' => 'ut-banner-info']`                     | the text block at the cursor has the class.                   |
| `['state' => 'listTight']`                               | the state of the editor holds (`assets/src/editor/tool-state.js`). |

For anything else, register a resolver on the JavaScript side. It takes precedence over `active`, and receives the
editor and the `args` of the tool:

```js
import { registerToolState } from '@umanit/tiptap-bundle';

registerToolState('toggleHighlight', (editor, args) => editor.isActive('highlight', args));
```

A tool without `active` nor resolver has no active state: it is an action, not a toggle.

The entries of the dropdowns of the bundle (a color, a size, a block style) tell when they are active the same way, so
the color, the size and the style of the selection are highlighted in their menu.

## Attributes on the field

The `attr` given to a `TiptapType` widget (`form_widget(form.body, {attr: {...}})`, or the `attr` option of the field)
are rendered on the `<textarea>` which holds the content, as for any field: a `data-*` attribute to target it from your
own Stimulus controller, an `aria-*`, a `class` (added to the ones of the editor), `rows`. A `true` value renders the
bare attribute, `false` and `null` render nothing; `id` and `name` are the editor's own. On the Twig component, the
same attributes are the `inputAttributes` prop. The attributes of the root element of the editor (`data-controller`…)
are not reachable from the form: wrap the field in your own element.

## Reaching the editor from your code

The editor is exposed on the root element of its controller (the one with `data-controller`), so your own Stimulus
controller does not have to ask the Stimulus application for it. `getTipTapEditor()` finds it from the root or from any
element inside (the textarea, a button of the toolbar…):

```js
import { getTipTapEditor } from '@umanit/tiptap-bundle';

const editor = getTipTapEditor(this.element); // a `TipTapEditor`, or null
editor.getEditor();                           // the TipTap `Editor`
editor.runCommand('toggleBold');              // the commands of the toolbar tools
```

The editor exists once its controller has connected. When your code may run before, listen to the event the root
dispatches (it bubbles, so `document` is enough):

```js
import { READY_EVENT } from '@umanit/tiptap-bundle';

document.addEventListener(READY_EVENT, ({ detail }) => detail.editor.getEditor().commands.focus());
```

The property is removed when the controller disconnects.

## Good to know

- **Registration time**: the registry is read when an editor connects (the `connect()` of its Stimulus controller).
  `registerExtension()`, `registerManager()` and `registerToolState()` only apply to the editors which connect
  afterwards; an editor which is already connected is not rebuilt.

  Called synchronously at the top level of the entrypoint, they are in time: Stimulus connects the controllers once
  the DOM is ready, after the modules are evaluated.

  ```js
  // assets/app.js
  import './bootstrap.js'; // starts Stimulus, which waits for the DOM
  import { registerExtension } from '@umanit/tiptap-bundle';
  import Highlight from '@tiptap/extension-highlight';

  registerExtension(Highlight); // before the first connect(): the editors get it
  ```

  They are too late when called after an `await`, a dynamic `import()` or a lazy-loaded module while an editor is
  already on the page. The editors added to the page afterwards (Turbo, a modal, a form collection) get them.
- **One module instance**: register through the package (`@umanit/tiptap-bundle`), the same module the controller
  imports, otherwise the registry you fill is not the one the editor reads.
- **Every editor of the page** gets the registered extensions, whatever its toolbar: an extension only adds to the
  schema, a tool is what shows it.
- **A wrong command fails loudly**: an unknown TipTap command or manager throws when the button is clicked. The bundle
  checks its own tools in a test (`assets/tests/toolbar-commands.test.js`, fed by a snapshot of the tools that
  PHPUnit keeps up to date); do the same for yours if they matter.
- **The HTML you add must survive the cleaners**: if you store a custom node or mark, the migration and cleaning
  tooling does not know it. Run `umanit:tiptap:audit` on contents which use it.
