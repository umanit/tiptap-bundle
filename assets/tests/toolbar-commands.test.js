import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';
import TipTapEditor from '../src/editor/tiptap-editor.js';
import { STATELESS_COMMANDS, TOOL_STATES } from '../src/editor/tool-state.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

const filesOf = (directory) => readdirSync(directory, { withFileTypes: true, recursive: true })
  .filter((entry) => entry.isFile() && entry.name.endsWith('.twig'))
  .map((entry) => join(entry.parentPath, entry.name));

const matches = (source, pattern) => [...source.matchAll(pattern)].map((match) => match[1]);

// A command is written in the templates as `command: 'name'` (a hash), `command="name"` (an attribute), or comes
// from the `Color` component.
const commandsOf = (files) => files.flatMap((file) => {
  const source = readFileSync(file, 'utf8');

  return [...matches(source, /command: '([\w.]+)'/g), ...matches(source, /command="([\w.]+)"/g)];
});

const components = join(root, 'templates', 'components');
const toolbarFiles = ['Tool', 'Color', 'FontSize', 'Styles'].map((name) => join(components, `${name}.html.twig`));
const colorCommands = matches(readFileSync(join(root, 'src', 'Twig', 'Components', 'Color.php'), 'utf8'), /=> '((?:un)?set\w+)'/g);

// What the buttons of the toolbar run, and what the modals and the context menu run
const toolbarCommands = [...new Set([...commandsOf(toolbarFiles), ...colorCommands])].sort();
const allCommands = [...new Set([...commandsOf(filesOf(components)), ...colorCommands])].sort();

let editor;

afterEach(() => {
  editor?.destroy();
  editor = null;
});

describe('the commands of the templates', () => {
  it('are found', () => {
    expect(toolbarCommands.length).toBeGreaterThan(30);
    expect(allCommands.length).toBeGreaterThan(toolbarCommands.length);
  });

  it.each(allCommands)('"%s" can be run by the editor', (command) => {
    editor = new TipTapEditor({}, { element: document.body });

    expect(editor.hasCommand(command)).toBe(true);
  });

  it.each(toolbarCommands)('"%s" declares whether the tool has an active state', (command) => {
    const stateful = command in TOOL_STATES;
    const stateless = STATELESS_COMMANDS.includes(command);

    expect(stateful !== stateless, `"${command}" must be in TOOL_STATES or in STATELESS_COMMANDS, not both`).toBe(true);
  });

  it('are the only ones the tool states talk about', () => {
    const declared = [...Object.keys(TOOL_STATES), ...STATELESS_COMMANDS];

    expect(declared.filter((command) => !toolbarCommands.includes(command))).toEqual([]);
  });
});
