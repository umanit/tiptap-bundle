import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Mark } from '@tiptap/core';
import TipTapEditor from '../src/editor/tiptap-editor.js';
import { registerExtension, registerManager, registerToolState } from '../src/index.js';
import { resetRegistry } from '../src/editor/registry.js';
import { isToolActive, parseToolArgs, resolveActive } from '../src/editor/tool-state.js';

let editor;

const create = (content = '', elements = {}, modal = {}) => {
  editor = new TipTapEditor(elements, { element: document.body, content }, {}, null, modal);

  return editor;
};

const Highlight = Mark.create({
  name: 'highlight',
  parseHTML: () => [{ tag: 'mark' }],
  renderHTML: ({ HTMLAttributes }) => ['mark', HTMLAttributes, 0],
  addCommands() {
    return {
      toggleHighlight: () => ({ commands }) => commands.toggleMark(this.name),
    };
  },
});

beforeEach(() => {
  document.body.innerHTML = '';
});

afterEach(() => {
  editor?.destroy();
  editor = null;
  resetRegistry();
});

describe('registered extensions', () => {
  it('are added to the editors created afterwards', () => {
    registerExtension(Highlight);
    create('<p>Hello <mark>world</mark></p>');

    expect(editor.getHTML()).toBe('<p>Hello <mark>world</mark></p>');
    expect(editor.hasCommand('toggleHighlight')).toBe(true);
  });

  it('are absent from the editors when none is registered', () => {
    create('<p>Hello</p>');

    expect(editor.hasCommand('toggleHighlight')).toBe(false);
  });

  it('give their commands to the toolbar', () => {
    registerExtension(Highlight);
    create('<p>Hello</p>');
    editor.getEditor().commands.selectAll();
    editor.runCommand('toggleHighlight');

    expect(editor.getHTML()).toBe('<p><mark>Hello</mark></p>');
  });
});

describe('registered managers', () => {
  it('receive the editor, the elements and the modal callbacks, and answer to <name>.<method>', () => {
    const open = vi.fn();
    const factory = vi.fn(() => ({ open }));
    const form = document.createElement('form');
    const modal = { open: vi.fn(), close: vi.fn() };

    registerManager('callout', factory);
    create('<p>Hello</p>', { anchorForm: form }, modal);

    expect(factory).toHaveBeenCalledWith(expect.objectContaining({
      editor: editor.getEditor(),
      elements: { anchorForm: form },
      modal,
    }));
    expect(editor.hasCommand('callout.open')).toBe(true);
    expect(editor.hasCommand('callout.close')).toBe(false);

    editor.runCommand('callout.open', 'args');

    expect(open).toHaveBeenCalledWith('args');
  });

  it('are destroyed with the editor', () => {
    const destroy = vi.fn();

    registerManager('callout', () => ({ destroy }));
    create();
    editor.destroy();

    expect(destroy).toHaveBeenCalledTimes(1);
    editor = null;
  });

  it('replace a manager of the bundle of the same name', () => {
    const open = vi.fn();

    registerManager('link', () => ({ open }));
    create();
    editor.runCommand('link.open');

    expect(open).toHaveBeenCalled();
  });

  it('refuse a name which cannot be told apart from a command', () => {
    expect(() => registerManager('a.b', () => ({}))).toThrow(/Invalid manager name/);
    expect(() => registerManager('', () => ({}))).toThrow(/Invalid manager name/);
  });

  it('fail loudly when a command is unknown', () => {
    create();

    expect(() => editor.runCommand('nope.open')).toThrow(/Unknown manager "nope.open"/);
    expect(() => editor.runCommand('nope')).toThrow(/Unknown TipTap command "nope"/);
  });
});

describe('modal callbacks', () => {
  it('reach the managers of the bundle through their constructor', () => {
    const form = document.createElement('form');
    const modal = { open: vi.fn(), close: vi.fn() };

    create('<p>Hello</p>', { anchorForm: form }, modal);
    editor.runCommand('anchor.open');

    expect(modal.open).toHaveBeenCalledWith(form);
  });
});

describe('tool states', () => {
  const stateOf = (content, active, selection = 2) => {
    create(content);
    editor.getEditor().commands.setTextSelection(selection);

    return resolveActive(editor, active);
  };

  it('tell when a node or a mark is active, with or without attributes', () => {
    expect(stateOf('<p><strong>Hello</strong></p>', { name: 'bold' })).toBe(true);
    expect(stateOf('<p>Hello</p>', { name: 'bold' })).toBe(false);
    expect(stateOf('<h2>Hello</h2>', { name: 'heading', attributes: { level: 2 } })).toBe(true);
    expect(stateOf('<h2>Hello</h2>', { name: 'heading', attributes: { level: 1 } })).toBe(false);
  });

  it('tell when an attribute is set, whatever the node', () => {
    expect(stateOf('<p style="text-align:center">Hello</p>', { attributes: { textAlign: 'center' } })).toBe(true);
    expect(stateOf('<p>Hello</p>', { attributes: { textAlign: 'center' } })).toBe(false);
  });

  it('tell when the selection has a color, a size, or the block a style', () => {
    const span = '<p><span class="ut-color-red">Hello</span></p>';

    expect(stateOf(span, { spanClass: 'ut-color-red' })).toBe(true);
    expect(stateOf(span, { spanClass: 'ut-color-blue' })).toBe(false);
    expect(stateOf('<p>Hello</p>', { spanClass: 'ut-color-red' })).toBe(false);
    expect(stateOf('<p class="ut-banner-info">Hello</p>', { blockClass: 'ut-banner-info' })).toBe(true);
    expect(stateOf('<h2 class="ut-a ut-banner-info">Hello</h2>', { blockClass: 'ut-banner-info' })).toBe(true);
    expect(stateOf('<p class="ut-a">Hello</p>', { blockClass: 'ut-banner-info' })).toBe(false);
    expect(stateOf('<p>Hello</p>', { blockClass: 'ut-banner-info' })).toBe(false);
  });

  it('tell a state of the editor', () => {
    expect(stateOf('<ul class="ut-list-tight"><li><p>Hello</p></li></ul>', { state: 'listTight' }, 4)).toBe(true);
    expect(stateOf('<p>Hello</p>', { state: 'listTight' })).toBe(false);
  });

  it('refuse a descriptor they do not understand', () => {
    create();

    expect(() => resolveActive(editor, { state: 'nope' })).toThrow(/Unknown editor state "nope"/);
    expect(() => resolveActive(editor, { nope: 1 })).toThrow(/Unknown key\(s\).*nope/);
    expect(() => resolveActive(editor, {})).toThrow(/needs a "name"/);
  });

  it('have no active state without descriptor nor registered resolver', () => {
    create();

    expect(isToolActive(editor, 'undo', null, null)).toBeNull();
  });

  it('come from the descriptor of the tool', () => {
    create('<p><strong>Hello</strong></p>');
    editor.getEditor().commands.setTextSelection(2);

    expect(isToolActive(editor, 'toggleBold', null, { name: 'bold' })).toBe(true);
  });

  it('can be registered by the application, which wins over the descriptor', () => {
    create('<p><strong>Hello</strong></p>');
    editor.getEditor().commands.setTextSelection(2);
    registerToolState('toggleBold', (_, args) => 'no' === args);

    expect(isToolActive(editor, 'toggleBold', 'yes', { name: 'bold' })).toBe(false);
    expect(isToolActive(editor, 'toggleBold', 'no', { name: 'bold' })).toBe(true);
  });

  it('read the arguments of a tool back', () => {
    expect(parseToolArgs(null)).toBeNull();
    expect(parseToolArgs('{"level":2}')).toEqual({ level: 2 });
    expect(parseToolArgs('"center"')).toBe('center');
    expect(parseToolArgs('ut-color-red')).toBe('ut-color-red');
  });
});
