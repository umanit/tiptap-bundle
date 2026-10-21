import {
  afterEach, beforeEach, describe, expect, it,
} from 'vitest';
import TipTapEditor from '../src/editor/tiptap-editor.js';

let editor;

const create = (content = '', elements = {}, ...rest) => {
  editor = new TipTapEditor(elements, { element: document.body, content }, {}, ...rest);

  return editor;
};

// Round-trips the HTML through the schema: what is kept is what the editor supports.
const roundTrip = (html) => create(html).getHTML();

beforeEach(() => {
  document.body.innerHTML = '';
});

afterEach(() => {
  editor?.destroy();
  editor = null;
});

describe('TipTapEditor', () => {
  it('adds the bundle class on the content element', () => {
    create('<p>Hello</p>');

    expect(document.querySelector('.ProseMirror').classList.contains('umanit-tiptap__content')).toBe(true);
  });

  it('exposes and updates the content', () => {
    create('<p>Hello</p>');
    editor.setContent('<p>World</p>');

    expect(editor.getHTML()).toBe('<p>World</p>');
  });

  it('runs editor commands and rejects unknown ones', () => {
    create('<p>Hello</p>');
    editor.getEditor().commands.selectAll();

    editor.runCommand('toggleBold');
    expect(editor.getHTML()).toBe('<p><strong>Hello</strong></p>');
    expect(editor.isActive('bold')).toBe(true);

    expect(() => editor.runCommand('nope')).toThrow('Unknown TipTap command "nope"');
    expect(() => editor.runCommand('nope.nope')).toThrow('Unknown manager "nope.nope"');
  });

  it('notifies the source mode changes', () => {
    create();
    const seen = [];
    editor.onSourceModeChange((enabled) => seen.push(enabled));

    editor.setSourceMode(true);

    expect(editor.isSourceMode()).toBe(true);
    expect(seen).toEqual([true]);
  });

  it('openFileManager requires an adapter', () => {
    create();

    expect(() => editor.openFileManager()).toThrow(/Missing "fileManagerAdapter"/);
  });

  it('overrides the modal hooks of every manager', () => {
    const form = document.createElement('form');
    form.innerHTML = '<input name="id">';
    const calls = [];
    create('<p>Hi</p>', { anchorForm: form }, null, {
      open: (el) => calls.push(['open', el]),
      close: (el) => calls.push(['close', el]),
    });

    editor.runCommand('anchor.open');
    editor.runCommand('anchor.confirm');

    expect(calls).toEqual([['open', form], ['close', form]]);
  });
});

describe('content schema', () => {
  it('keeps text color, background color and size classes', () => {
    expect(roundTrip('<p><span class="ut-color-red">a</span></p>')).toBe('<p><span class="ut-color-red">a</span></p>');
    expect(roundTrip('<p><span class="ut-bg-red">a</span></p>')).toBe('<p><span class="ut-bg-red">a</span></p>');
    expect(roundTrip('<p><span class="ut-fs-20">a</span></p>')).toBe('<p><span class="ut-fs-20">a</span></p>');
  });

  it('does not mistake a foreign "bg-*" class for a background color', () => {
    expect(roundTrip('<p><span class="bg-red">a</span></p>')).toBe('<p>a</p>');
  });

  it('maps the alignment to bundle classes and reads inline styles', () => {
    expect(roundTrip('<p class="ut-text-center">a</p>')).toBe('<p class="ut-text-center">a</p>');
    expect(roundTrip('<p style="text-align: right">a</p>')).toBe('<p class="ut-text-right">a</p>');
  });

  it('keeps block classes but strips Word noise', () => {
    expect(roundTrip('<p class="ut-banner-info MsoNormal">a</p>')).toBe('<p class="ut-banner-info">a</p>');
    expect(roundTrip('<h2 class="ut-banner-warning">a</h2>')).toBe('<h2 class="ut-banner-warning">a</h2>');
  });

  it('keeps anchors and converts CKEditor anchors', () => {
    expect(roundTrip('<p id="foo">a</p>')).toBe('<p id="foo">a</p>');
    expect(roundTrip('<p><a id="foo"></a>a</p>')).toBe('<p id="foo">a</p>');
  });

  it('keeps table classes without inline styles', () => {
    const html = roundTrip('<table class="ut-table-striped ut-w-50" border="1"><tbody><tr>'
      + '<td class="ut-bg-red" colwidth="100">a</td></tr></tbody></table>');

    expect(html).toContain('class="ut-table-striped ut-w-50"');
    expect(html).toContain('border="1"');
    expect(html).toContain('class="ut-bg-red"');
    expect(html).not.toContain('style=');
    expect(html).toContain('<colgroup><col width="100"></colgroup>');
  });

  it('renders column widths as <col width>, read back from a colgroup', () => {
    const html = roundTrip('<table><colgroup><col width="120"><col></colgroup><tbody><tr><td>a</td><td>b</td></tr>'
      + '</tbody></table>');

    expect(html).toContain('<colgroup><col width="120"><col></colgroup>');
    expect(html).not.toContain('style=');
  });

  it('redraws the table when its class changes', () => {
    create('<table><tbody><tr><td>a</td></tr></tbody></table>');
    editor.getEditor().chain().setTextSelection(3).updateAttributes('table', { class: 'ut-w-50' })
      .run();

    expect(document.querySelector('.ProseMirror table').classList.contains('ut-w-50')).toBe(true);
  });

  it('sets the width of the whole selected column from the cell form', () => {
    const cellForm = document.createElement('form');
    cellForm.innerHTML = '<input name="colWidth"><input name="bgColor"><select name="verticalAlign"></select>';
    document.body.append(cellForm);
    create('<table><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></tbody></table>', { cellForm });
    editor.getEditor().commands.setTextSelection(7);
    cellForm.elements.colWidth.value = '150';
    editor.runCommand('table.confirmUpdateCell');

    expect(editor.getHTML()).toContain('<colgroup><col><col width="150"></colgroup>');
    expect(editor.getEditor().state.doc.firstChild.child(1).child(1).attrs.colwidth).toEqual([150]);

    // Every change shows on screen, not only the first one
    const cols = () => [...document.querySelectorAll('.ProseMirror colgroup col')].map((c) => c.getAttribute('width'));
    expect(cols()).toEqual([null, '150']);

    cellForm.elements.colWidth.value = '200';
    editor.runCommand('table.confirmUpdateCell');

    expect(cols()).toEqual([null, '200']);

    cellForm.elements.colWidth.value = '';
    editor.runCommand('table.confirmUpdateCell');

    expect(document.querySelector('.ProseMirror colgroup')).toBeNull();

    expect(editor.getHTML()).not.toContain('colgroup');
  });

  it('turns the alignment of a cell into a class, once', () => {
    const html = roundTrip('<table><tbody><tr><td style="text-align:center">a</td><th align="right">b</th>'
      + '<td class="ut-text-left" style="text-align:center">c</td><td>d</td></tr></tbody></table>');

    expect(html).toContain('<td class="ut-text-center">');
    expect(html).toContain('<th class="ut-text-right">');
    expect(html).toContain('<td class="ut-text-left">');
    expect(html).not.toContain('style=');
    expect(html).toContain('<td><p>d</p></td>');
  });

  it('sets the classes of the cell form without losing the other ones', () => {
    const cellForm = document.createElement('form');
    cellForm.innerHTML = '<input name="colWidth"><input type="radio" name="bgColor" value="ut-bg-red">'
      + '<select name="verticalAlign"><option value=""></option></select>'
      + '<select name="textAlign"><option value=""></option><option value="ut-text-center">c</option></select>';
    document.body.append(cellForm);
    create(
      '<table><tbody><tr><td class="keep ut-bg-blue">a</td><td class="ut-align-top">b</td></tr></tbody></table>',
      { cellForm },
    );
    const classes = () => editor.getEditor().state.doc.firstChild.firstChild.child(0).attrs.class;

    editor.getEditor().commands.setTextSelection(4);
    cellForm.elements.textAlign.value = 'ut-text-center';
    editor.runCommand('table.confirmUpdateCell');

    expect(classes()).toBe('keep ut-text-center');

    cellForm.elements.textAlign.value = '';
    editor.runCommand('table.confirmUpdateCell');

    expect(classes()).toBe('keep');
  });

  it('keeps the alignment class of a table', () => {
    expect(roundTrip('<table class="ut-w-50 ut-table-center"><tbody><tr><td>a</td></tr></tbody></table>'))
      .toContain('class="ut-w-50 ut-table-center"');
  });

  it('keeps image classes and sizes', () => {
    const html = roundTrip('<p><img src="/a.png" alt="A" width="10" class="ut-float-start"></p>');

    expect(html).toContain('src="/a.png"');
    expect(html).toContain('class="ut-float-start"');
    expect(html).toContain('width="10"');
  });

  it('does not render links with a target by default but keeps them', () => {
    expect(roundTrip('<p><a href="/foo">a</a></p>')).toContain('href="/foo"');
  });

  describe('list items', () => {
    it('accepts a heading as first child of a list item', () => {
      expect(roundTrip('<ul><li><h2>Title</h2></li></ul>')).toBe('<ul><li><h2>Title</h2></li></ul>');
    });

    it('parses bare and paragraph-wrapped list items into the same model', () => {
      expect(roundTrip('<ul><li>Text</li></ul>')).toBe('<ul><li><p>Text</p></li></ul>');
      expect(roundTrip('<ul><li><p>Text</p></li></ul>')).toBe('<ul><li><p>Text</p></li></ul>');
    });

    it('turns a list item paragraph into a heading and back without leaving the list', () => {
      create('<ul><li><p>Text</p></li></ul>');
      editor.getEditor().commands.setTextSelection(3);

      editor.runCommand('toggleHeading', { level: 2 });
      expect(editor.getHTML()).toContain('<ul><li><h2>Text</h2></li></ul>');

      editor.runCommand('setParagraph');
      expect(editor.getHTML()).toContain('<ul><li><p>Text</p></li></ul>');
    });

    it('splits a heading list item with Enter', () => {
      create('<ul><li><h2>One</h2></li></ul>');
      const tip = editor.getEditor();
      tip.commands.focus('end');
      tip.commands.splitListItem('listItem');

      expect(tip.getJSON().content[0].content).toHaveLength(2);
    });
  });
});
