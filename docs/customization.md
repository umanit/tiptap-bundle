# Customizing the editor from your application

Three kinds of customization, from the lightest to the deepest:

1. **Compose** what the bundle already provides: the toolbar, the styles, the colors and the templates are built by
   services you replace (see the [README](../README.md#customizing-the-editor)).
2. **Add a tool** to the toolbar: a button which runs a command.
3. **Add a behavior** to the editor: a TipTap extension, or a manager which opens a modal and talks to the document.

Adding a tool takes a PHP side (the button) and a JavaScript side (what it runs).

## 1. A tool which runs a TipTap command

Take the `Highlight` extension of TipTap (`@tiptap/extension-highlight`).

Register the extension **before the first editor connects**: at the top level of the entrypoint of your application,
which imports the package.

```js
// assets/app.js
import Highlight from '@tiptap/extension-highlight';
import { registerExtension, registerToolState } from '@umanit/tiptap-bundle';

registerExtension(Highlight);

// Optional: when the tool shows as active at the cursor
registerToolState('toggleHighlight', (editor) => editor.isActive('highlight'));
```

Then declare the tool in your toolbar builder. A `Tool` which has a `command` is a tool of your application: it is
rendered as a button which runs the command.

```php
use Umanit\TiptapBundle\DTO\Tool;
use Umanit\TiptapBundle\DTO\ToolbarSection;

new ToolbarSection([
    new Tool('highlight', command: 'toggleHighlight', title: 'Highlight', icon: 'app:highlighter'),
]);
```

| Argument  | Role                                                                                           |
|-----------|------------------------------------------------------------------------------------------------|
| `name`    | Identifies the tool (`data-wysiwyg-tool`). Without `command`, it selects a tool of the bundle. |
| `command` | A TipTap command (`toggleHighlight`) or `<manager>.<method>` (below).                          |
| `title`   | The tooltip and the accessible name of the button. Required, and translated by you.            |
| `icon`    | Name of a [UX icon](https://ux.symfony.com/icons) (`app:highlighter`).                         |
| `label`   | Text of the button. Hidden visually, the icon is shown instead.                                |
| `args`    | The argument of the command: a string, or an array rendered as JSON (`['color' => 'yellow']`). |

A tool of your application which has the name of a tool of the bundle replaces it.

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
new Tool('callout', command: 'callout.insert', title: 'Callout', args: ['text' => 'Note']);
```

The factory is called once per editor with:

| Key                  | Content                                                                                 |
|----------------------|-----------------------------------------------------------------------------------------|
| `editor`             | The TipTap `Editor`.                                                                    |
| `elements`           | The forms and groups of the editor which the bundle found (`linkForm`, `imageForm`…).   |
| `fileManagerAdapter` | The file manager adapter of the editor, or `null`.                                      |
| `modal`              | `{ open(element), close(element) }`: open or close the modal which contains an element. |

A manager registered under the name of a manager of the bundle (`table`, `link`, `image`, `template`, `clipboard`,
`anchor`) replaces it. A name cannot contain a dot.

## The active state of a tool

The button of a tool gets the class `active` when `registerToolState(command, resolver)` says so. The resolver receives
the editor and the `args` of the tool, and returns a boolean. The commands of the bundle are declared in
`assets/src/editor/tool-state.js`; one which is not declared has no active state.

## Good to know

- **Registration time**: the registry is read when an editor connects (the `connect()` of its Stimulus controller).
  `registerExtension()`, `registerManager()` and `registerToolState()` only apply to the editors which connect
  afterwards; an editor which is already connected is not rebuilt.

  Called synchronously at the top level of the entrypoint, they are in time: Stimulus connects the controllers once the
  DOM is ready, after the modules are evaluated.

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
  checks its own templates in a test (`assets/tests/toolbar-commands.test.js`); do the same for yours if they matter.
- **The HTML you add must survive the cleaners**: if you store a custom node or mark, the migration and cleaning tooling
  does not know it. Run `umanit:tiptap:audit` on contents which use it.
