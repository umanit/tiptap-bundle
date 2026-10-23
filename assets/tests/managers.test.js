import {
  afterEach, beforeEach, describe, expect, it, vi,
} from 'vitest';
import TipTapEditor from '../src/editor/tiptap-editor.js';
import { MODAL_PROPERTY } from '../src/constants.js';

let tiptap;

const form = (html) => {
  const el = document.createElement('form');
  el.innerHTML = html;
  document.body.appendChild(el);

  return el;
};

const create = (elements, content = '<p>Hello</p>', ...rest) => {
  tiptap = new TipTapEditor(elements, { element: document.body, content }, {}, ...rest);

  return tiptap;
};

beforeEach(() => {
  document.body.innerHTML = '';
});

afterEach(() => {
  tiptap?.destroy();
  tiptap = null;
});

describe('modal hooks', () => {
  it('opens and closes the modal controller exposed on the dialog', () => {
    const dialog = document.createElement('dialog');
    const modal = { open: vi.fn(), close: vi.fn() };
    dialog[MODAL_PROPERTY] = modal;
    const anchorForm = document.createElement('form');
    anchorForm.innerHTML = '<input name="id">';
    dialog.appendChild(anchorForm);
    document.body.appendChild(dialog);

    create({ anchorForm });
    tiptap.runCommand('anchor.open');
    tiptap.runCommand('anchor.confirm');

    expect(modal.open).toHaveBeenCalledOnce();
    expect(modal.close).toHaveBeenCalledOnce();
  });
});

describe('link manager', () => {
  const elements = () => ({
    linkForm: form(`
      <input type="radio" name="type" value="url" checked><input type="radio" name="type" value="anchor">
      <div id="url"><input name="url"></div><div id="anchor"><input name="anchor"></div>
      <select name="target"><option value=""></option><option value="_blank">blank</option></select>`),
  });

  it('inserts a link', () => {
    const els = elements();
    create(els, '<p>Hello</p>', null, { open: () => {}, close: () => {} });
    tiptap.getEditor().commands.selectAll();
    els.linkForm.elements.url.value = '/foo';
    els.linkForm.elements.target.value = '_blank';

    tiptap.runCommand('link.confirm');

    expect(tiptap.getHTML()).toContain('href="/foo"');
    expect(tiptap.getHTML()).toContain('target="_blank"');
  });

  it('adds no target nor rel to a link which does not have any', () => {
    const els = elements();
    create(els, '<p><a href="/foo">Hello</a></p>', null, { open: () => {}, close: () => {} });

    expect(tiptap.getHTML()).toContain('<a href="/foo">Hello</a>');

    tiptap.getEditor().commands.selectAll();
    els.linkForm.elements.url.value = '/bar';
    tiptap.runCommand('link.confirm');

    expect(tiptap.getHTML()).toContain('href="/bar"');
    expect(tiptap.getHTML()).not.toMatch(/target=|rel=/);
  });

  it('sets the rel only when the box is checked, and shows it when editing', () => {
    const els = elements();
    els.linkForm.insertAdjacentHTML('beforeend', '<input type="checkbox" name="rel" value="noopener noreferrer">');
    create(els, '<p>Hello</p>', null, { open: () => {}, close: () => {} });
    tiptap.getEditor().commands.selectAll();
    els.linkForm.elements.url.value = '/foo';
    els.linkForm.elements.rel.checked = true;
    tiptap.runCommand('link.confirm');

    expect(tiptap.getHTML()).toContain('rel="noopener noreferrer"');

    tiptap.runCommand('link.open');

    expect(els.linkForm.elements.rel.checked).toBe(true);

    els.linkForm.elements.rel.checked = false;
    tiptap.runCommand('link.confirm');

    expect(tiptap.getHTML()).not.toContain('rel=');
  });

  it('builds an internal link from an anchor, adding the hash', () => {
    const els = elements();
    create(els, '<p>Hello</p>', null, { open: () => {}, close: () => {} });
    tiptap.getEditor().commands.selectAll();
    els.linkForm.elements.type.value = 'anchor';
    els.linkForm.elements.anchor.value = 'section';

    tiptap.runCommand('link.confirm');

    expect(tiptap.getHTML()).toContain('href="#section"');
  });

  it('removes the link when the url is empty', () => {
    const els = elements();
    create(els, '<p><a href="/foo">Hello</a></p>', null, { open: () => {}, close: () => {} });
    tiptap.getEditor().commands.selectAll();

    tiptap.runCommand('link.confirm');

    expect(tiptap.getHTML()).toBe('<p>Hello</p>');
  });

  it('dispatches an event on the editor', () => {
    const els = elements();
    create(els, '<p>Hello</p>', null, { open: () => {}, close: () => {} });
    const listener = vi.fn();
    document.addEventListener('tiptap:link:removed', listener);

    tiptap.runCommand('link.unset');
    document.removeEventListener('tiptap:link:removed', listener);

    expect(listener).toHaveBeenCalledOnce();
  });
});

describe('image manager', () => {
  const imageForm = () => form(`
    <input name="url"><input name="alt"><input name="width"><input name="height">
    <select name="align"><option value=""></option><option value="left">l</option><option value="center">c</option></select>
    <select name="margin"><option value=""></option><option value="ut-ma-5">5</option></select>`);

  it('inserts an image with alignment and margin classes', () => {
    const els = { imageForm: imageForm() };
    create(els, '', null, { open: () => {}, close: () => {} });
    els.imageForm.elements.url.value = '/a.png';
    els.imageForm.elements.alt.value = 'A';
    els.imageForm.elements.align.value = 'center';
    els.imageForm.elements.margin.value = 'ut-ma-5';

    tiptap.runCommand('image.confirm');

    expect(tiptap.getHTML()).toContain('src="/a.png"');
    expect(tiptap.getHTML()).toContain('class="ut-block ut-mx-auto ut-ma-5"');
  });

  it('edits the selected image and keeps the classes the form does not manage', () => {
    const els = { imageForm: imageForm() };
    create(els, '<p><img src="/a.png" alt="A" width="100" class="ut-float-start ut-ma-5 custom"></p>', null, {
      open: () => {},
      close: () => {},
    });
    tiptap.runCommand('setNodeSelection', 1);

    tiptap.runCommand('image.open');

    expect(els.imageForm.elements.url.value).toBe('/a.png');
    expect(els.imageForm.elements.align.value).toBe('left');
    expect(els.imageForm.elements.margin.value).toBe('ut-ma-5');

    els.imageForm.elements.alt.value = 'B';
    tiptap.runCommand('image.confirm');

    expect(tiptap.getHTML()).toContain('alt="B"');
    expect(tiptap.getHTML()).toContain('class="custom ut-float-start ut-ma-5"');
    expect(tiptap.getHTML().match(/<img/g)).toHaveLength(1);
  });

  it('does not insert anything without url', () => {
    create({ imageForm: imageForm() }, '', null, { open: () => {}, close: () => {} });

    tiptap.runCommand('image.confirm');

    expect(tiptap.getHTML()).toBe('<p></p>');
  });

  it('fills the url from the file manager', () => {
    const els = { imageForm: imageForm() };
    const adapter = (onSelect) => onSelect('/uploads/a.png');
    create(els, '', adapter);

    tiptap.openFileManager();

    expect(els.imageForm.elements.url.value).toBe('/uploads/a.png');
  });
});

describe('template manager', () => {
  const templateForm = () => form('<input type="checkbox" name="replaceContent">');
  const hooks = { open: () => {}, close: () => {} };

  it('inserts the template content', () => {
    create({ templateForm: templateForm() }, '<p>Hello</p>', null, hooks);

    tiptap.runCommand('template.insert', '<h1>Title</h1>');

    expect(tiptap.getHTML()).toContain('<h1>Title</h1>');
    expect(tiptap.getHTML()).toContain('Hello');
  });

  it('replaces the content on demand', () => {
    const els = { templateForm: templateForm() };
    create(els, '<p>Hello</p>', null, hooks);
    els.templateForm.elements.replaceContent.checked = true;

    tiptap.runCommand('template.insert', '<h1>Title</h1>');

    expect(tiptap.getHTML()).toContain('<h1>Title</h1>');
    expect(tiptap.getHTML()).not.toContain('Hello');
  });
});

describe('anchor manager', () => {
  it('sets and removes the id of the current block', () => {
    const anchorForm = form('<input name="id">');
    create({ anchorForm }, '<p>Hello</p>', null, { open: () => {}, close: () => {} });
    anchorForm.elements.id.value = ' foo ';

    tiptap.runCommand('anchor.confirm');
    expect(tiptap.getHTML()).toBe('<p id="foo">Hello</p>');

    tiptap.runCommand('anchor.remove');
    expect(tiptap.getHTML()).toBe('<p>Hello</p>');
  });
});
