import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import EditorController from '../src/controllers/editor_controller.js';
import { registerToolState } from '../src/index.js';
import { resetRegistry } from '../src/editor/registry.js';

let application;

const mount = async (html) => {
  document.body.innerHTML = html;
  application = Application.start();
  application.register('umanit--tiptap--editor', EditorController);
  await new Promise((resolve) => {
    setTimeout(resolve, 20);
  });
};

const markup = `
  <form id="host">
    <div data-controller="umanit--tiptap--editor" data-umanit--tiptap--editor-translations-value="{}">
      <template data-umanit--tiptap--editor-target="modals">
        <dialog><form data-umanit--tiptap--editor-target="linkForm"><input name="url"></form></dialog>
      </template>
      <div data-umanit--tiptap--editor-target="toolbar"></div>
      <div class="umanit-tiptap__surface"><div data-umanit--tiptap--editor-target="editor"></div></div>
      <textarea name="body" data-umanit--tiptap--editor-target="input"><p>Hello</p></textarea>
      <ol data-umanit--tiptap--editor-target="path"></ol>
      <button type="button" data-umanit--tiptap--editor-target="resizeHandle"
        data-action="keydown->umanit--tiptap--editor#resizeWithKeyboard"></button>
    </div>
    <button type="submit" id="save">Save</button>
  </form>`;

beforeEach(() => {
  document.body.innerHTML = '';
});

afterEach(() => {
  application?.stop();
  resetRegistry();
});

describe('editor controller', () => {
  it('mounts the editor from the textarea content', async () => {
    await mount(markup);

    expect(document.querySelector('.ProseMirror').innerHTML).toBe('<p>Hello</p>');
  });

  it('shows the path of the elements around the cursor', async () => {
    await mount(markup.replace('<p>Hello</p>', '<ul><li><p><strong>Hello</strong></p></li></ul>'));

    const { editor } = document.querySelector('.ProseMirror');
    editor.commands.setTextSelection(4);

    const path = [...document.querySelectorAll('ol > li')].map((item) => item.textContent);

    expect(path).toEqual(['ul', 'li', 'p', 'strong']);
  });

  it('resizes the editing area with the keyboard, down to a minimum', async () => {
    await mount(markup);

    // jsdom has no layout: the area is given the height its stylesheet would
    document.querySelector('.umanit-tiptap__surface').getBoundingClientRect = () => ({ height: 120 });

    const handle = document.querySelector('button');
    const root = document.querySelector('[data-controller]');
    const press = (key) => handle.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));

    press('ArrowDown');
    expect(root.style.getPropertyValue('--umanit-tiptap-height')).toBe('160px');

    press('ArrowUp');
    expect(root.style.getPropertyValue('--umanit-tiptap-height')).toBe('120px');
  });

  it('marks the tools which are active at the cursor, from their command', async () => {
    const button = (command, args = '') => `<button data-wysiwyg-tool="x"
      data-umanit--tiptap--editor-command-param="${command}"
      ${args ? `data-umanit--tiptap--editor-args-param='${args}'` : ''}></button>`;

    await mount(markup
      .replace('<p>Hello</p>', '<h2><strong>Hello</strong></h2>')
      .replace(
        '<div data-umanit--tiptap--editor-target="toolbar"></div>',
        `<div data-umanit--tiptap--editor-target="toolbar">
          ${button('toggleBold')}${button('toggleItalic')}${button('toggleHeading', '{"level":2}')}
          ${button('toggleHeading', '{"level":1}')}${button('undo')}${button('toggleHighlight')}
        </div>`,
      ));

    document.querySelector('.ProseMirror').editor.commands.setTextSelection(2);

    const active = [...document.querySelectorAll('[data-wysiwyg-tool]')].map((el) => el.classList.contains('active'));

    expect(active).toEqual([true, false, true, false, false, false]);
  });

  it('marks the tools of the application from the state they registered', async () => {
    registerToolState('toggleHighlight', (editor, args) => editor.isActive('bold') && 'yellow' === args.color);

    await mount(markup
      .replace('<p>Hello</p>', '<p><strong>Hello</strong></p>')
      .replace(
        '<div data-umanit--tiptap--editor-target="toolbar"></div>',
        `<div data-umanit--tiptap--editor-target="toolbar"><button data-wysiwyg-tool="highlight"
          data-umanit--tiptap--editor-command-param="toggleHighlight"
          data-umanit--tiptap--editor-args-param='{"color":"yellow"}'></button></div>`,
      ));

    document.querySelector('.ProseMirror').editor.commands.setTextSelection(2);

    expect(document.querySelector('[data-wysiwyg-tool]').classList.contains('active')).toBe(true);
  });

  it('mounts the modals without leaking their fields into the host form', async () => {
    await mount(markup);

    const host = document.getElementById('host');

    expect(document.querySelector('template')).toBeNull();
    expect(document.querySelector('dialog')).not.toBeNull();
    expect([...new FormData(host).keys()]).toEqual(['body']);
    expect(document.getElementById('save').form).toBe(host);
  });

  it('keeps the textarea in sync with the editor', async () => {
    await mount(markup);

    const pm = document.querySelector('.ProseMirror');
    const { editor } = pm;
    editor.commands.setContent('<p>Changed</p>', { emitUpdate: true });

    expect(document.querySelector('textarea').value).toBe('<p>Changed</p>');
  });

  it('shows a laid out source and submits it compact', async () => {
    await mount(markup.replace('<p>Hello</p>', '<ul><li><p>A</p></li></ul><p>B</p>'));

    const input = document.querySelector('textarea');
    const host = document.getElementById('host');
    const controller = application.getControllerForElementAndIdentifier(
      document.querySelector('[data-controller]'),
      'umanit--tiptap--editor',
    );

    controller.toggleSource();

    expect(input.value).toBe('<ul>\n  <li>\n    <p>A</p>\n  </li>\n</ul>\n<p>B</p>');

    host.addEventListener('submit', (event) => event.preventDefault());
    host.dispatchEvent(new Event('submit', { cancelable: true }));

    expect(input.value).toBe('<ul><li><p>A</p></li></ul><p>B</p>');
  });

  it('gives the compact HTML back when leaving the source view', async () => {
    await mount(markup.replace('<p>Hello</p>', '<p>A</p><p>B</p>'));

    const input = document.querySelector('textarea');
    const controller = application.getControllerForElementAndIdentifier(
      document.querySelector('[data-controller]'),
      'umanit--tiptap--editor',
    );

    controller.toggleSource();
    controller.toggleSource();

    expect(input.value).toBe('<p>A</p><p>B</p>');
  });

  it('replaces its content, textarea included, on the set-content event', async () => {
    await mount(markup);

    const root = document.querySelector('[data-controller]');
    root.dispatchEvent(new CustomEvent('umanit-tiptap:set-content', { detail: { content: '<p>Injected</p>' } }));

    expect(document.querySelector('.ProseMirror').innerHTML).toBe('<p>Injected</p>');
    expect(document.querySelector('textarea').value).toBe('<p>Injected</p>');
  });

  it('clears its content when the set-content event has no detail', async () => {
    await mount(markup);

    document.querySelector('[data-controller]').dispatchEvent(new CustomEvent('umanit-tiptap:set-content'));

    expect(document.querySelector('textarea').value).toBe('<p></p>');
  });

  describe('migration diff', () => {
    const diffMarkup = (content) => `
      <div data-controller="umanit--tiptap--editor"
           data-umanit--tiptap--editor-migration-diff-value="true"
           data-umanit--tiptap--editor-translations-value='{"diffLost":"Lost: {signals}","diffRewritten":"Rewritten"}'>
        <div class="ut-hidden" data-umanit--tiptap--editor-target="diffBanner">
          <span data-umanit--tiptap--editor-target="diffMessage"></span>
        </div>
        <div data-umanit--tiptap--editor-target="toolbar"></div>
        <div data-umanit--tiptap--editor-target="editor"></div>
        <textarea data-umanit--tiptap--editor-target="input">${content}</textarea>
      </div>`;

    const banner = () => document.querySelector('[data-umanit--tiptap--editor-target="diffBanner"]');

    it('stays silent when the editor keeps the content as is', async () => {
      await mount(diffMarkup('&lt;p&gt;Hello&lt;/p&gt;'));

      expect(banner().classList.contains('ut-hidden')).toBe(true);
    });

    it('warns about what the editor drops', async () => {
      await mount(diffMarkup('&lt;div data-x=&quot;1&quot;&gt;&lt;p&gt;x&lt;/p&gt;&lt;/div&gt;'));

      expect(banner().classList.contains('ut-hidden')).toBe(false);
      expect(banner().textContent).toContain('Lost: tag:div');
    });

    it('only mentions a rewrite when nothing is lost', async () => {
      await mount(diffMarkup('&lt;p&gt;&lt;b&gt;x&lt;/b&gt;&lt;/p&gt;'));

      expect(banner().classList.contains('ut-hidden')).toBe(false);
      expect(banner().textContent).toContain('Rewritten');
    });

    it('does nothing when the option is off', async () => {
      await mount(
        diffMarkup('&lt;div&gt;x&lt;/div&gt;').replace('migration-diff-value="true"', 'migration-diff-value="false"'),
      );

      expect(banner().classList.contains('ut-hidden')).toBe(true);
    });
  });
});
