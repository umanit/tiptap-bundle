import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Mark } from '@tiptap/core';
import TipTapEditor from '../src/editor/tiptap-editor.js';
import { registerExtension, registerManager, registerToolState } from '../src/index.js';
import { resetRegistry } from '../src/editor/registry.js';
import { getToolState, parseToolArgs } from '../src/editor/tool-state.js';

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
  it('are declared for the commands of the bundle', () => {
    expect(getToolState('toggleBold')).toBeTypeOf('function');
    expect(getToolState('undo')).toBeUndefined();
  });

  it('can be registered by the application, and win over the ones of the bundle', () => {
    const resolver = () => true;

    registerToolState('toggleHighlight', resolver);
    registerToolState('toggleBold', resolver);

    expect(getToolState('toggleHighlight')).toBe(resolver);
    expect(getToolState('toggleBold')).toBe(resolver);
  });

  it('read the arguments of a tool back', () => {
    expect(parseToolArgs(null)).toBeNull();
    expect(parseToolArgs('{"level":2}')).toEqual({ level: 2 });
    expect(parseToolArgs('"center"')).toBe('center');
    expect(parseToolArgs('ut-color-red')).toBe('ut-color-red');
  });
});
