import { afterEach, beforeEach, describe, expect, it } from 'vitest';
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

  it('keeps a foreign "bg-*" class as a custom class, not as a background color', () => {
    create('<p><span class="bg-red">a</span></p>');

    expect(editor.getHTML()).toBe('<p><span class="bg-red">a</span></p>');
    expect(editor.getEditor().getJSON().content[0].content[0].marks.map((mark) => mark.type))
      .toEqual(['textSpan']);
  });

  it('replaces the alignment of a paragraph read from its class instead of adding a second one', () => {
    create('<p class="ut-text-center keep">a</p>');
    editor.getEditor()
      .chain()
      .focus()
      .setTextAlign('left')
      .run();

    expect(editor.getHTML()).toBe('<p class="keep ut-text-left">a</p>');
  });

  it('keeps the classes of the items, quotes, code, rules and divs', () => {
    expect(roundTrip('<blockquote class="q"><p>a</p></blockquote>'))
      .toBe('<blockquote class="q"><p>a</p></blockquote>');
    expect(roundTrip('<pre class="p"><code>a</code></pre>')).toBe('<pre class="p"><code>a</code></pre>');
    expect(roundTrip('<p><code class="c">a</code></p>')).toBe('<p><code class="c">a</code></p>');
    expect(roundTrip('<p>a</p><hr class="h"><p>b</p>')).toBe('<p>a</p><hr class="h"><p>b</p>');
    expect(roundTrip('<ul><li class="i"><p>a</p></li></ul>')).toBe('<ul><li class="i"><p>a</p></li></ul>');
  });

  it('keeps a div which has a class, nested or not, and drops the wrapper without one', () => {
    expect(roundTrip('<div class="box"><p>a</p><div class="in"><p>b</p></div></div>'))
      .toBe('<div class="box"><p>a</p><div class="in"><p>b</p></div></div>');
    expect(roundTrip('<div class="box">a</div>')).toBe('<div class="box"><p>a</p></div>');
    expect(roundTrip('<div><p>a</p></div>')).toBe('<p>a</p>');
    expect(roundTrip('<div class="WordSection1"><p>a</p></div>')).toBe('<p>a</p>');
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
    const html = roundTrip('<table class="ut-table-striped ut-w-50" border="1" cellpadding="3"><tbody><tr>'
      + '<td class="ut-bg-red" colwidth="100">a</td></tr></tbody></table>');

    expect(html).toContain('class="ut-table-striped ut-w-50"');
    // The look of the table is the one of the site: no border, padding or spacing of the table itself
    expect(html).not.toMatch(/border|cellpadding|cellspacing/);
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

  it('updates the table from its form: an emptied width and class remove the old ones', () => {
    const tableForm = document.createElement('form');
    tableForm.innerHTML = '<select name="tableWidth"><option value=""></option><option value="ut-w-50">50</option>'
      + '</select><select name="tableAlign"><option value=""></option></select><input name="cssClass">';
    document.body.append(tableForm);
    create('<table class="ut-w-50 striped"><tbody><tr><td>a</td></tr></tbody></table>', { tableForm });
    editor.getEditor().commands.setTextSelection(3);

    editor.runCommand('table.openEditModal');
    expect(tableForm.elements.tableWidth.value).toBe('ut-w-50');
    expect(tableForm.elements.cssClass.value).toBe('striped');

    tableForm.elements.tableWidth.value = '';
    tableForm.elements.cssClass.value = '';
    editor.runCommand('table.confirmAdd');

    expect(editor.getEditor().state.doc.firstChild.attrs.class).toBeNull();
    expect(editor.getHTML()).toContain('<table>');
  });

  it('turns the background color of a cell into a palette class in legacy import mode', () => {
    const legacyStyles = { backgroundColor: { '#eeeeee': 'ut-bg-grey-light' } };
    const cells = (attributes) => `<table><tbody><tr><td ${attributes}>a</td></tr></tbody></table>`;
    const withMap = (html) => {
      editor?.destroy();
      editor = new TipTapEditor({}, { element: document.body, content: html, legacyStyles }, {});

      return editor.getHTML();
    };

    expect(withMap(cells('style="background-color:#eeeeee"'))).toContain('<td class="ut-bg-grey-light">');
    expect(withMap(cells('style="background-color:rgb(238, 238, 238)"'))).toContain('<td class="ut-bg-grey-light">');
    expect(withMap(cells('style="background:#EEE"'))).toContain('<td class="ut-bg-grey-light">');
    expect(withMap(cells('class="keep" style="background-color:#eeeeee"')))
      .toContain('<td class="keep ut-bg-grey-light">');
    expect(withMap(cells('class="ut-bg-red" style="background-color:#eeeeee"'))).toContain('<td class="ut-bg-red">');
    expect(withMap(cells('style="background-color:#123456"'))).toContain('<td>');
    expect(withMap('<table><tbody><tr><th style="background-color:#eeeeee">a</th></tr></tbody></table>'))
      .toContain('<th class="ut-bg-grey-light">');
    // Without a map the schema reads classes only
    expect(roundTrip(cells('style="background-color:#eeeeee"'))).toContain('<td>');
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

  it('drops the classes Word leaves on a table and its cells, and keeps the others', () => {
    const html = roundTrip('<table class="MsoTableGrid ut-w-50 keep"><tbody><tr>'
      + '<td class="MsoNormal">a</td><td class="SCXW1 ut-bg-red">b</td></tr></tbody></table>');

    expect(html).toContain('<table class="ut-w-50 keep">');
    expect(html).toContain('<td>');
    expect(html).toContain('<td class="ut-bg-red">');
    expect(html).not.toMatch(/Mso|SCX/);
    expect(roundTrip('<table class="MsoTableGrid"><tbody><tr><td>a</td></tr></tbody></table>'))
      .toContain('<table>');
  });

  it('turns the align attribute of a table into a class, once', () => {
    const table = (attributes) => `<table ${attributes}><tbody><tr><td>a</td></tr></tbody></table>`;

    expect(roundTrip(table('align="center"'))).toContain('<table class="ut-table-center">');
    expect(roundTrip(table('align="right" class="ut-w-50"'))).toContain('<table class="ut-w-50 ut-table-right">');
    expect(roundTrip(table('align="right" class="ut-table-center"'))).toContain('<table class="ut-table-center">');
    expect(roundTrip(table('align="left"'))).toContain('<table>');
  });

  it('keeps the alignment class of a table', () => {
    expect(roundTrip('<table class="ut-w-50 ut-table-center"><tbody><tr><td>a</td></tr></tbody></table>'))
      .toContain('class="ut-w-50 ut-table-center"');
  });

  it('drops the classes Word leaves on an image', () => {
    expect(roundTrip('<p><img src="/a.png" class="MsoNormal ut-float-start keep"></p>'))
      .toContain('class="ut-float-start keep"');
    expect(roundTrip('<p><img src="/a.png" class="SCXW1"></p>')).not.toContain('class=');
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
    describe('alignment of an item', () => {
      const legacyStyles = { fontSize: { '12px': 'ut-fs-12' } };
      const legacyTrip = (html) => {
        editor = new TipTapEditor({}, { element: document.body, content: html, legacyStyles }, {});

        return editor.getHTML();
      };

      it('reports it on the paragraph the editor creates for an inline text', () => {
        expect(roundTrip('<ul><li style="text-align:justify">a</li></ul>'))
          .toBe('<ul class="ut-list-tight"><li><p class="ut-text-justify">a</p></li></ul>');
        expect(roundTrip('<ol><li style="text-align:right">a</li></ol>'))
          .toBe('<ol class="ut-list-tight"><li><p class="ut-text-right">a</p></li></ol>');
        expect(roundTrip('<ul><li style="text-align:center">a</li></ul>')).toContain('<p class="ut-text-center">a</p>');
      });

      it('reports it on the paragraphs of the item, the own alignment of a paragraph winning', () => {
        expect(roundTrip('<ul><li style="text-align:justify"><p>a</p></li></ul>'))
          .toBe('<ul><li><p class="ut-text-justify">a</p></li></ul>');
        expect(roundTrip('<ul><li style="text-align:justify"><p>a</p><p>b</p></li></ul>'))
          .toBe('<ul><li><p class="ut-text-justify">a</p><p class="ut-text-justify">b</p></li></ul>');
        expect(roundTrip('<ul><li style="text-align:justify"><p style="text-align:center">a</p></li></ul>'))
          .toBe('<ul><li><p class="ut-text-center">a</p></li></ul>');
        expect(roundTrip('<ul><li style="text-align:justify"><p class="ut-text-right">a</p></li></ul>'))
          .toBe('<ul><li><p class="ut-text-right">a</p></li></ul>');
      });

      it('leaves the items of a sub-list their own alignment', () => {
        expect(roundTrip('<ul><li style="text-align:justify">a<ul><li>b</li></ul></li></ul>')).toContain(
          '<li><p class="ut-text-justify">a</p><ul class="ut-list-tight"><li><p>b</p></li></ul></li>',
        );
        expect(roundTrip('<ul><li>a<ul><li style="text-align:right">b</li></ul></li></ul>')).toContain(
          '<li><p>a</p><ul class="ut-list-tight"><li><p class="ut-text-right">b</p></li></ul></li>',
        );
      });

      it('reaches a text in a span converted to a class', () => {
        expect(legacyTrip('<ul><li style="text-align:justify"><span style="font-size:12px">a</span></li></ul>'))
          .toContain('<li><p class="ut-text-justify"><span class="ut-fs-12">a</span></p></li>');
      });

      it('adds nothing for the default alignment nor for an unknown value', () => {
        ['start', 'left', 'foo'].forEach((value) => {
          expect(roundTrip(`<ul><li style="text-align:${value}">a</li></ul>`)).not.toContain('ut-text-');
        });
      });
    });

    it('accepts a heading as first child of a list item', () => {
      expect(roundTrip('<ul><li><h2>Title</h2></li></ul>')).toBe('<ul><li><h2>Title</h2></li></ul>');
    });

    it('parses bare and paragraph-wrapped list items into the same items', () => {
      expect(roundTrip('<ul><li>Text</li></ul>')).toBe('<ul class="ut-list-tight"><li><p>Text</p></li></ul>');
      expect(roundTrip('<ul><li><p>Text</p></li></ul>')).toBe('<ul><li><p>Text</p></li></ul>');
    });

    it('marks a list whose items were all written bare as tight', () => {
      expect(roundTrip('<ul><li>One</li><li>Two<ul><li>Nested</li></ul></li></ul>')).toBe(
        '<ul class="ut-list-tight"><li><p>One</p></li>'
        + '<li><p>Two</p><ul class="ut-list-tight"><li><p>Nested</p></li></ul></li></ul>',
      );
      expect(roundTrip('<ol><li>One</li></ol>')).toBe('<ol class="ut-list-tight"><li><p>One</p></li></ol>');
    });

    it('leaves a list with a block in an item untouched, and keeps the class once marked', () => {
      expect(roundTrip('<ul><li><p>One</p></li></ul>')).toBe('<ul><li><p>One</p></li></ul>');
      expect(roundTrip('<ul><li>One</li><li><p>Two</p></li></ul>'))
        .toBe('<ul><li><p>One</p></li><li><p>Two</p></li></ul>');
      expect(roundTrip('<ul class="ut-list-tight"><li><p>One</p></li></ul>'))
        .toBe('<ul class="ut-list-tight"><li><p>One</p></li></ul>');
    });

    it('toggles the tight class on the closest list only, and reports it', () => {
      create('<ul><li><p>One</p><ul><li><p>Nested</p></li></ul></li></ul>');
      const tip = editor.getEditor();

      tip.commands.setTextSelection(3);
      expect(editor.isListTight()).toBe(false);

      editor.runCommand('toggleListTight');
      expect(editor.isListTight()).toBe(true);
      expect(editor.getHTML()).toContain('<ul class="ut-list-tight"><li><p>One</p><ul><li>');

      tip.commands.setTextSelection(10);
      expect(editor.isListTight()).toBe(false);

      tip.commands.setTextSelection(3);
      editor.runCommand('toggleListTight');
      expect(editor.isListTight()).toBe(false);
      expect(editor.getHTML()).toContain('<ul><li><p>One</p>');
    });

    it('makes a list created by an indent tight when the parent list is', () => {
      create('<ul class="ut-list-tight"><li>One</li><li>Two</li></ul>');
      const tip = editor.getEditor();

      tip.commands.setTextSelection(10);
      editor.runCommand('sinkListItem', 'listItem');

      expect(editor.getHTML()).toContain(
        '<ul class="ut-list-tight"><li><p>One</p><ul class="ut-list-tight"><li><p>Two</p></li></ul></li></ul>',
      );
    });

    it('leaves a list created by an indent as it is when the parent list is not tight', () => {
      create('<ul><li><p>One</p></li><li><p>Two</p></li></ul>');
      editor.getEditor().commands.setTextSelection(10);
      editor.runCommand('sinkListItem', 'listItem');

      expect(editor.getHTML()).toContain('<ul><li><p>One</p><ul><li><p>Two</p></li></ul></li></ul>');
    });

    it('keeps the class of an existing sublist when an item is indented into it', () => {
      create('<ul class="ut-list-tight"><li><p>One</p><ul><li><p>Two</p></li></ul></li><li><p>Three</p></li></ul>');
      const tip = editor.getEditor();

      tip.commands.setTextSelection(tip.state.doc.content.size - 3);
      editor.runCommand('sinkListItem', 'listItem');

      expect(editor.getHTML()).toContain('<li><p>One</p><ul><li><p>Two</p></li><li><p>Three</p></li></ul></li>');
    });

    it('does nothing outside a list', () => {
      create('<p>Text</p>');

      expect(editor.getEditor().commands.toggleListTight()).toBe(false);
      expect(editor.isListTight()).toBe(false);
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
