import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';
import TipTapEditor from '../src/editor/tiptap-editor.js';
import { resolveActive } from '../src/editor/tool-state.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// The tools of the bundle, as the PHP side lists them (a PHPUnit test keeps the snapshot up to date)
const tools = JSON.parse(readFileSync(join(__dirname, 'fixtures', 'default-tools.json'), 'utf8'));

const filesOf = (directory) => readdirSync(directory, { withFileTypes: true, recursive: true })
  .filter((entry) => entry.isFile() && entry.name.endsWith('.twig'))
  .map((entry) => join(entry.parentPath, entry.name));

const matches = (source, pattern) => [...source.matchAll(pattern)].map((match) => match[1]);

// Besides the tools, the templates have buttons which run commands too: the modals, the context menu and the
// entries of the dropdowns. Written as `command: 'name'` (a hash), `command="name"` (an attribute), or by the
// `Color` component.
const templateCommands = [...new Set([
  ...filesOf(join(root, 'templates', 'components')).flatMap((file) => {
    const source = readFileSync(file, 'utf8');

    return [...matches(source, /command: '([\w.]+)'/g), ...matches(source, /command="([\w.]+)"/g)];
  }),
  ...matches(readFileSync(join(root, 'src', 'Twig', 'Components', 'Color.php'), 'utf8'), /=> '((?:un)?set\w+)'/g),
])].sort();

const buttons = tools.filter(({ command }) => null !== command);

let editor;

afterEach(() => {
  editor?.destroy();
  editor = null;
});

describe('the tools of the bundle', () => {
  it('are found', () => {
    expect(buttons.length).toBeGreaterThan(30);
    expect(tools.some(({ component }) => null !== component)).toBe(true);
  });

  it.each(buttons.map(({ name, command }) => [name, command]))(
    '"%s" runs "%s", which the editor knows',
    (_, command) => {
      editor = new TipTapEditor({}, { element: document.body });

      expect(editor.hasCommand(command)).toBe(true);
    },
  );

  it.each(buttons.filter(({ active }) => null !== active).map(({ name, active }) => [name, active]))(
    '"%s" declares an active state the editor understands',
    (_, active) => {
      editor = new TipTapEditor({}, { element: document.body });

      expect(typeof resolveActive(editor, active)).toBe('boolean');
    },
  );
});

describe('the commands of the templates', () => {
  it('are found', () => {
    expect(templateCommands.length).toBeGreaterThan(15);
  });

  it.each(templateCommands)('"%s" can be run by the editor', (command) => {
    editor = new TipTapEditor({}, { element: document.body });

    expect(editor.hasCommand(command)).toBe(true);
  });
});
