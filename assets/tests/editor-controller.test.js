import {
  afterEach, beforeEach, describe, expect, it,
} from 'vitest';
import { Application } from '@hotwired/stimulus';
import EditorController from '../src/controllers/editor_controller.js';

let application;

const mount = async (html) => {
  document.body.innerHTML = html;
  application = Application.start();
  application.register('umanit--tiptap--editor', EditorController);
  await new Promise((resolve) => { setTimeout(resolve, 20); });
};

const markup = `
  <form id="host">
    <div data-controller="umanit--tiptap--editor" data-umanit--tiptap--editor-translations-value="{}">
      <template data-umanit--tiptap--editor-target="modals">
        <dialog><form data-umanit--tiptap--editor-target="linkForm"><input name="url"></form></dialog>
      </template>
      <div data-umanit--tiptap--editor-target="toolbar"></div>
      <div data-umanit--tiptap--editor-target="editor"></div>
      <textarea name="body" data-umanit--tiptap--editor-target="input"><p>Hello</p></textarea>
    </div>
    <button type="submit" id="save">Save</button>
  </form>`;

beforeEach(() => {
  document.body.innerHTML = '';
});

afterEach(() => {
  application?.stop();
});

describe('editor controller', () => {
  it('mounts the editor from the textarea content', async () => {
    await mount(markup);

    expect(document.querySelector('.ProseMirror').innerHTML).toBe('<p>Hello</p>');
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
});
