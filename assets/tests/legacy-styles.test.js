import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import TipTapEditor from '../src/editor/tiptap-editor.js';
import RoundTripper from '../src/audit/round-trip.js';
import { normalizeColor, normalizeFontSize, resolveLegacyClass } from '../src/editor/legacy-styles.js';

const palette = {
  color: { '#ff0000': 'ut-color-red', '#0000FF': 'ut-color-blue' },
  backgroundColor: { 'rgb(255, 255, 0)': 'ut-bg-yellow' },
  fontSize: { '14px': 'ut-fs-14', '12pt': 'ut-fs-16' },
};

describe('normalizeColor', () => {
  it.each([
    ['#F00', '#ff0000'],
    ['#FF0000', '#ff0000'],
    ['rgb(255, 0, 0)', '#ff0000'],
    ['rgb(255 0 0)', '#ff0000'],
    ['rgba(255,0,0,1)', '#ff0000'],
    ['  Red ', '#ff0000'],
    ['rebeccapurple', '#663399'],
    ['DarkGrey', '#a9a9a9'],
    ['transparent', 'transparent'],
    ['constructor', 'constructor'],
    ['rgba(255,0,0,0.5)', 'rgba(255,0,0,0.5)'],
    ['', null],
    [null, null],
  ])('%s => %s', (input, expected) => {
    expect(normalizeColor(input)).toBe(expected);
  });
});

describe('normalizeFontSize', () => {
  it.each([
    ['14px', '14px'],
    ['10.5pt', '14px'],
    ['12PT', '16px'],
    ['11pt', '14.67px'],
    ['1.2em', '1.2em'],
    ['', null],
  ])('%s => %s', (input, expected) => {
    expect(normalizeFontSize(input)).toBe(expected);
  });
});

describe('resolveLegacyClass', () => {
  const el = (style) => {
    const element = document.createElement('span');
    element.setAttribute('style', style);

    return element;
  };

  it('does nothing without a map', () => {
    expect(resolveLegacyClass(null, 'color', el('color:#f00'))).toBeNull();
  });

  it('matches normalized values on both sides', () => {
    expect(resolveLegacyClass(palette, 'color', el('color:#f00'))).toBe('ut-color-red');
    expect(resolveLegacyClass(palette, 'color', el('color:rgb(0,0,255)'))).toBe('ut-color-blue');
    expect(resolveLegacyClass(palette, 'backgroundColor', el('background-color:#ffff00'))).toBe('ut-bg-yellow');
    expect(resolveLegacyClass(palette, 'fontSize', el('font-size:16px'))).toBe('ut-fs-16');
  });

  it('attaches a named color to the class of its hex value', () => {
    expect(resolveLegacyClass(palette, 'color', el('color:red'))).toBe('ut-color-red');
    expect(resolveLegacyClass(palette, 'color', el('color:Blue'))).toBe('ut-color-blue');
    expect(resolveLegacyClass(palette, 'backgroundColor', el('background-color:yellow'))).toBe('ut-bg-yellow');
    expect(resolveLegacyClass(palette, 'color', el('color:green'))).toBeNull();
  });

  it('returns null for a value which is not in the map', () => {
    expect(resolveLegacyClass(palette, 'color', el('color:#123456'))).toBeNull();
  });
});

describe('legacy import mode in the editor', () => {
  let editor;

  const load = (html, legacyStyles) => {
    editor = new TipTapEditor({}, { element: document.body, content: html, legacyStyles }, {});

    return editor.getHTML();
  };

  beforeEach(() => {
    document.body.innerHTML = '';
  });

  afterEach(() => {
    editor?.destroy();
  });

  it('drops inline styles by default', () => {
    expect(load('<p><span style="color:#ff0000;font-size:14px">a</span></p>')).toBe('<p>a</p>');
  });

  it('converts a color, a background and a size to their class', () => {
    expect(load('<p><span style="color:#ff0000">a</span></p>', palette))
      .toBe('<p><span class="ut-color-red">a</span></p>');
    expect(load('<p><span style="background-color:rgb(255,255,0)">a</span></p>', palette))
      .toBe('<p><span class="ut-bg-yellow">a</span></p>');
    expect(load('<p><span style="font-size:14px">a</span></p>', palette))
      .toBe('<p><span class="ut-fs-14">a</span></p>');
    expect(load('<p><span style="font-size:12pt">a</span></p>', palette))
      .toBe('<p><span class="ut-fs-16">a</span></p>');
  });

  it('drops a value which is not in the map', () => {
    expect(load('<p><span style="color:#123456">a</span></p>', palette)).toBe('<p>a</p>');
  });

  it('keeps the classes as they are', () => {
    expect(load('<p><span class="ut-color-red">a</span></p>', palette))
      .toBe('<p><span class="ut-color-red">a</span></p>');
  });

  it('converts a span which carries several properties', () => {
    const html = load('<p><span style="color:#ff0000;background-color:#ffff00;font-size:14px">a</span></p>', palette);

    expect(html).toContain('ut-color-red');
    expect(html).toContain('ut-bg-yellow');
    expect(html).toContain('ut-fs-14');
    expect(html).toContain('>a<');
  });

  it('converts a style carried by a formatting element', () => {
    const html = load('<p><strong style="font-size:10pt"><em>a</em></strong></p>', {
      fontSize: { '10pt': 'ut-fs-13' },
    });

    expect(html).toContain('ut-fs-13');
    expect(html).toContain('<strong');
    expect(html).toContain('<em');
    expect(html).toContain('>a<');
  });
});

describe('custom classes on inline elements', () => {
  let editor;

  const load = (html) => {
    document.body.innerHTML = '';
    editor = new TipTapEditor({}, { element: document.body, content: html }, {});

    return editor.getHTML();
  };

  afterEach(() => {
    editor?.destroy();
  });

  it('keeps the class of a span', () => {
    expect(load('<p><span class="text-primary">a</span></p>')).toBe('<p><span class="text-primary">a</span></p>');
  });

  it('keeps the class of a formatting element as a span around the text', () => {
    const html = load('<p><strong class="text-secondary"><em>a</em></strong></p>');

    expect(html).toContain('text-secondary');
    expect(html).toContain('<strong');
    expect(html).toContain('<em');
  });

  it('keeps the spans of the source as they are, nested or not', () => {
    const nested = '<p><span class="text-primary"><span class="ut-fs-14">a</span></span> b</p>';
    const flat = '<p><span class="ut-color-red text-primary">a</span></p>';

    expect(load(nested)).toBe(nested);
    expect(load(flat)).toBe(flat);
  });

  it('puts the span around the formatting element', () => {
    expect(load('<p><strong class="text-secondary"><em>a</em></strong></p>'))
      .toBe('<p><span class="text-secondary"><strong><em>a</em></strong></span></p>');
  });

  it('strips the noise of Word but not the classes of the site', () => {
    expect(load('<p><span class="ut-color-red text-primary MsoNormal">a</span></p>'))
      .toBe('<p><span class="ut-color-red text-primary">a</span></p>');
  });
});

describe('toolbar commands on the spans', () => {
  let editor;

  const run = (html, command, args, from = 1, to = 2) => {
    document.body.innerHTML = '';
    editor = new TipTapEditor({}, { element: document.body, content: html }, {});
    editor.getEditor().commands.setTextSelection({ from, to });
    editor.runCommand(command, args);

    return editor.getHTML();
  };

  afterEach(() => {
    editor?.destroy();
  });

  it('sets a color on a text, in a span', () => {
    expect(run('<p>a</p>', 'setTextColor', 'ut-color-red')).toBe('<p><span class="ut-color-red">a</span></p>');
  });

  it('replaces the color of the span which has one, and keeps its other classes', () => {
    expect(run('<p><span class="text-primary ut-color-red">a</span></p>', 'setTextColor', 'ut-color-blue'))
      .toBe('<p><span class="text-primary ut-color-blue">a</span></p>');
  });

  it('puts the class in the span of the text rather than adding one', () => {
    expect(run('<p><span class="text-primary">a</span></p>', 'setTextSize', 'ut-fs-14'))
      .toBe('<p><span class="text-primary ut-fs-14">a</span></p>');
  });

  it('does not touch the nesting of the spans', () => {
    const html = '<p><span class="a"><span class="ut-color-red">x</span></span></p>';

    expect(run(html, 'setTextColor', 'ut-color-blue')).toBe(html.replace('red', 'blue'));
  });

  it('removes the class of a family and the span which is left empty', () => {
    expect(run('<p><span class="ut-color-red ut-fs-14">a</span></p>', 'unsetTextColor'))
      .toBe('<p><span class="ut-fs-14">a</span></p>');
    expect(run('<p><span class="ut-fs-14">a</span></p>', 'unsetTextSize')).toBe('<p>a</p>');
  });

  it('only changes the selected text', () => {
    expect(run('<p><span class="ut-color-red">ab</span></p>', 'setTextColor', 'ut-color-blue', 1, 2))
      .toBe('<p><span class="ut-color-blue">a</span><span class="ut-color-red">b</span></p>');
  });

  it('tells whether the selection has a class', () => {
    run('<p><span class="ut-color-red">a</span>b</p>', 'unsetTextSize');

    expect(editor.hasSpanClass('ut-color-red')).toBe(true);
    expect(editor.hasSpanClass('ut-color-blue')).toBe(false);

    editor.getEditor().commands.setTextSelection({ from: 1, to: 3 });

    expect(editor.hasSpanClass('ut-color-red')).toBe(false);
  });
});

describe('round trip with the legacy styles', () => {
  it('does not report a converted style as lost, but reports an unmapped one', () => {
    const roundTripper = new RoundTripper(window, palette);
    const lostOf = (html) => roundTripper.run(html).lost.map(({ signal }) => signal);

    expect(lostOf('<p><span style="color:#ff0000;font-size:14px">a</span></p>')).toEqual([]);
    expect(lostOf('<p><span style="color:#123456">a</span></p>')).toEqual(['style:color']);

    roundTripper.destroy();
  });
});

describe('bin/roundtrip.js --legacy-styles', () => {
  const bin = path.resolve(import.meta.dirname, '../bin/roundtrip.js');
  const input = `${JSON.stringify({ html: '<p><span style="color:#ff0000">a</span></p>' })}\n`;

  it('converts with the given palette', () => {
    const file = path.join(mkdtempSync(path.join(tmpdir(), 'legacy-')), 'palette.json');
    writeFileSync(file, JSON.stringify(palette));

    const { status, stdout } = spawnSync(
      process.execPath,
      [bin, `--legacy-styles=${file}`],
      { input, encoding: 'utf8' },
    );

    expect(status).toBe(0);
    expect(JSON.parse(stdout)).toMatchObject({ after: '<p><span class="ut-color-red">a</span></p>', lost: [] });
  });

  it('fails on an unreadable file', () => {
    const { status, stderr } = spawnSync(
      process.execPath,
      [bin, '--legacy-styles=/nonexistent.json'],
      { input, encoding: 'utf8' },
    );

    expect(status).toBe(1);
    expect(stderr).toContain('Cannot read the legacy styles');
  });
});

describe('inline styles inventory', () => {
  it('lists the distinct normalized colors, backgrounds and sizes', async () => {
    const { default: collect } = await import('../src/audit/inline-styles.js');
    const html = '<p style="color:#F00;font-size:10.5pt"><span style="color:rgb(255,0,0)">a</span>'
      + '<span style="background-color:#ffff00;font-size:14px">b</span></p><p style="text-align:left">c</p>';

    expect(collect(html, new DOMParser())).toEqual({
      color: ['#ff0000'],
      backgroundColor: ['#ffff00'],
      fontSize: ['14px'],
    });
  });

  it('is reported by the round trip', () => {
    const roundTripper = new RoundTripper(window);

    expect(roundTripper.run('<p><span style="color:#00f">a</span></p>').inlineStyles.color).toEqual(['#0000ff']);

    roundTripper.destroy();
  });
});

describe('column widths read on the cells', () => {
  let editor;

  const importHtml = (html, legacyStyles = {}) => {
    editor = new TipTapEditor({}, {
      element: document.createElement('div'), content: html, legacyStyles, trailingNode: false,
    }, {});

    return editor.getHTML();
  };

  const table = (...rows) => `<table><tbody>${rows.map((cells) => `<tr>${cells.join('')}</tr>`)
    .join('')}</tbody></table>`;
  const cols = (html) => [...html.matchAll(/<col(?: width="(\d+)")?>/g)].map(([, width]) => width ?? null);

  afterEach(() => editor?.destroy());

  it('turns the widths of the cells into the widths of the columns', () => {
    expect(importHtml(table(['<td style="width:274px">a</td>', '<td style="width:227px">b</td>'])))
      .toBe('<table><colgroup><col width="274"><col width="227"></colgroup><tbody><tr><td><p>a</p></td>'
        + '<td><p>b</p></td></tr></tbody></table>');
  });

  it('reads the width attribute as the style', () => {
    expect(cols(importHtml(table(['<td width="120">a</td>', '<td width="90px">b</td>'])))).toEqual(['120', '90']);
  });

  it('takes the most frequent width of a column, the largest one on a tie, and skips the stray ones', () => {
    const rows = [['1px', '400px'], ['350px', '400px'], ['350px', '400px']]
      .map((widths) => widths.map((width) => `<td style="width:${width}">a</td>`));

    expect(cols(importHtml(table(...rows)))).toEqual(['350', '400']);
    expect(cols(importHtml(table(
      ['<td width="100">a</td>', '<td>b</td>'],
      ['<td width="200">a</td>', '<td>b</td>'],
    )))).toEqual(['200', null]);
  });

  it('ignores another unit', () => {
    expect(importHtml(table(['<td style="width:50%">a</td>', '<td>b</td>']))).not.toContain('<col');
  });

  it('lets a <col width> come first', () => {
    const html = '<table><colgroup><col width="100"><col></colgroup><tbody><tr><td style="width:200px">a</td>'
      + '<td style="width:300px">b</td></tr></tbody></table>';

    expect(cols(importHtml(html))).toEqual(['100', '300']);
  });

  it('does not invent a width for the columns of a merged cell', () => {
    expect(importHtml(table(
      ['<td colspan="2" style="width:300px">a</td>'],
      ['<td>b</td>', '<td>c</td>'],
    ))).not.toContain('<col');
  });

  it('only reads the widths in legacy import mode', () => {
    expect(importHtml(table(['<td style="width:274px">a</td>']), null)).not.toContain('<col');
  });

  it('reports a width it did not convert, not the converted ones', () => {
    const roundTripper = new RoundTripper(window, {});
    const lost = (cells) => roundTripper.run(table(...cells)).lost.map(({ signal }) => signal);

    try {
      expect(lost([['<td style="width:274px">a</td>', '<td width="120">b</td>']])).toEqual([]);
      expect(lost([['<td style="width:50%">a</td>', '<td>b</td>']])).toEqual(['style:width']);
      expect(lost([['<td width="1">a</td>', '<td>b</td>']])).toEqual(['attr:td@width']);
    } finally {
      roundTripper.destroy();
    }
  });
});
