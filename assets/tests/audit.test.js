import { spawnSync } from 'node:child_process';
import path from 'node:path';
import {
  afterEach, beforeEach, describe, expect, it,
} from 'vitest';
import RoundTripper from '../src/audit/round-trip.js';
import { collectSignals, lostSignals } from '../src/audit/signals.js';

const parser = new DOMParser();
const signalsOf = (html) => Object.fromEntries(collectSignals(html, parser).signals);

describe('collectSignals', () => {
  it('counts tags, attributes, style families and classes', () => {
    const html = '<p class="a b" style="border-top-width:1px;border-left-width:1px;color:red">'
      + '<a href="#" id="x">l</a></p>';

    expect(signalsOf(html))
      .toEqual({
        'tag:p': 1,
        'class:a': 1,
        'class:b': 1,
        'style:border*': 1,
        'style:color': 1,
        'tag:a': 1,
        'attr:a@href': 1,
        'attr:a@id': 1,
      });
  });

  it('treats <b> and <i> as <strong> and <em>', () => {
    expect(signalsOf('<p><b>x</b><i>y</i></p>')).toEqual({ 'tag:p': 1, 'mark:strong': 1, 'mark:em': 1 });
  });

  it('measures marks in covered characters, not in elements', () => {
    expect(signalsOf('<p><strong>ab</strong><strong>cd</strong></p>')['mark:strong']).toBe(4);
    expect(signalsOf('<p><strong>abcd</strong></p>')['mark:strong']).toBe(4);
  });

  it('ignores a <span> and counts a utility class as its inline style', () => {
    expect(signalsOf('<p class="ut-text-center"><span class="ut-color-red">x</span></p>')).toEqual({
      'tag:p': 1,
      'class:ut-text-center': 1,
      'style:text-align': 1,
      'class:ut-color-red': 1,
      'style:color': 1,
    });
  });

  it('counts the inline size of an image as the attributes the editor keeps', () => {
    const expected = {
      'tag:p': 1, 'tag:img': 1, 'attr:img@src': 1, 'attr:img@height': 1, 'attr:img@width': 1,
    };

    expect(signalsOf('<p><img src="a.jpg" style="height:403px;width:783px;"></p>')).toEqual(expected);
    expect(signalsOf('<p><img src="a.jpg" height="403" width="783"></p>')).toEqual(expected);
    expect(signalsOf('<p><img src="a.jpg" height="403" style="height:403px" width="783"></p>')).toEqual(expected);
  });

  it('reports an inline image size which has no attribute equivalent', () => {
    expect(signalsOf('<p><img src="a.jpg" style="width:50%"></p>')['style:img@width']).toBe(1);
  });

  it('counts the inline styles the editor reads as marks as those marks', () => {
    const inline = signalsOf(
      '<p><span style="font-weight:bold;font-style:italic;text-decoration:underline">ab</span></p>',
    );

    expect(inline).toEqual({
      'tag:p': 1, 'mark:strong': 2, 'mark:em': 2, 'mark:u': 2,
    });
    expect(signalsOf('<p><span style="font-weight:normal">a</span></p>')['style:font-weight']).toBe(1);
  });

  it('counts the float and margins of an image as the utility classes the editor writes', () => {
    expect(signalsOf('<p><img src="a" style="float:left;margin:10px"></p>')).toEqual({
      'tag:p': 1, 'tag:img': 1, 'attr:img@src': 1,
    });
    expect(signalsOf('<p><img src="a" style="display:block;margin-left:auto;margin-right:auto"></p>')).toEqual({
      'tag:p': 1, 'tag:img': 1, 'attr:img@src': 1,
    });
    expect(signalsOf('<p><img src="a" style="margin:7px"></p>')['style:margin-top']).toBe(1);
  });

  it('counts a margin-left in multiples of 40px as an indentation level', () => {
    expect(signalsOf('<p style="margin-left:80px">a</p>')).toEqual({ 'tag:p': 1 });
    expect(signalsOf('<p style="margin-left:50px">a</p>')['style:margin-left']).toBe(1);
    expect(signalsOf('<ul style="margin-left:40px"><li>a</li></ul>')['style:margin-left']).toBeUndefined();
    expect(signalsOf('<li style="margin-left:40px">a</li>')['style:margin-left']).toBe(1);
  });

  it('counts a zero margin as a class: inline, it overrides the margin the site gives to lists', () => {
    expect(signalsOf('<ul style="margin-left:0;margin-right:0px"><li>a</li></ul>'))
      .toEqual({ 'tag:ul': 1, 'tag:li': 1 });
    expect(signalsOf('<p style="margin-top:0">a</p>')['style:margin-top']).toBe(1);
  });

  it('compares the text without whitespace', () => {
    expect(collectSignals('<p>a  b</p><p>c</p>', parser).text).toBe('abc');
  });
});

describe('lostSignals', () => {
  it('reports the signals which decreased', () => {
    const before = new Map([['a', 2], ['b', 1], ['c', 1]]);
    const after = new Map([['a', 1], ['b', 1], ['d', 3]]);

    expect(lostSignals(before, after)).toEqual([
      { signal: 'a', before: 2, after: 1 },
      { signal: 'c', before: 1, after: 0 },
    ]);
  });
});

describe('RoundTripper', () => {
  let roundTripper;

  beforeEach(() => {
    roundTripper = new RoundTripper(window);
  });

  afterEach(() => {
    roundTripper.destroy();
  });

  const lostOf = (html) => roundTripper.run(html).lost.map(({ signal }) => signal);

  it('reports nothing for a content the schema supports', () => {
    const result = roundTripper.run('<h2>Title</h2><p>Some <strong>bold</strong> text</p>');

    expect(result.lost).toEqual([]);
    expect(result.textChanged).toBe(false);
  });

  it('keeps the column widths in pixels and reports the ones it cannot', () => {
    const table = (cols) => `<table><colgroup>${cols}</colgroup><tbody><tr><td>a</td><td>b</td></tr></tbody></table>`;

    expect(lostOf(table('<col width="100"><col width="200px">'))).toEqual([]);
    expect(lostOf(table('<col width="50%"><col width="50%">'))).toEqual(['tag:colgroup', 'tag:col', 'attr:col@width']);
    expect(lostOf(table('<col style="width:100px"><col>'))).toContain('style:width');
  });

  it('does not report the alignment of a cell', () => {
    const cells = '<td style="text-align:center">a</td><td>b</td>';

    expect(lostOf(`<table><tbody><tr>${cells}</tr></tbody></table>`)).toEqual([]);
  });

  it('does not report an alignment converted to a class, nor <b> rewritten as <strong>', () => {
    const result = roundTripper.run('<p style="text-align:center"><b>x</b></p>');

    expect(result.after).toBe('<p class="ut-text-center"><strong>x</strong></p>');
    expect(result.lost).toEqual([]);
  });

  it('converts what the audit counts as kept: marks, image float and margins, indentation', () => {
    const result = (html) => roundTripper.run(html);

    expect(result('<p><span style="font-weight:bold">a</span><span style="text-decoration:line-through">b</span></p>'))
      .toMatchObject({ after: '<p><strong>a</strong><s>b</s></p>', lost: [] });
    expect(result('<p style="margin-left:80px">a</p>')).toMatchObject({
      after: '<p class="ut-indent-2">a</p>', lost: [],
    });
    expect(result('<h2 style="margin-left:40px;text-align:center">a</h2>').after)
      .toContain('class="ut-indent-1 ut-text-center"');

    expect(result('<ul style="margin-left:0;margin-right:0;"><li><p>a</p></li></ul>')).toMatchObject({
      after: '<ul class="ut-indent-0 ut-me-0"><li><p>a</p></li></ul>', lost: [],
    });
    expect(result('<ol style="margin-left:80px"><li><p>a</p></li></ol>').after).toContain('<ol class="ut-indent-2">');

    const image = result('<img src="a.jpg" style="float:left;margin:10px">');

    expect(image.lost).toEqual([]);
    expect(image.after).toContain('class="ut-float-start ut-ma-10"');
    expect(result('<img src="a.jpg" style="display:block;margin-left:auto;margin-right:auto">').after)
      .toContain('class="ut-block ut-mx-auto"');
  });

  it('reports what has no class: another margin, an indentation off the 40px steps', () => {
    expect(lostOf('<p style="margin-left:50px">a</p>')).toEqual(['style:margin-left']);
    expect(lostOf('<img src="a.jpg" style="margin:7px">')).toContain('style:margin-top');
  });

  it('keeps an image in its paragraph, and wraps a bare one in a paragraph', () => {
    const html = '<p>a <img src="a.jpg" alt=""> b</p>';

    expect(roundTripper.run(html).after).toBe(html);
    expect(roundTripper.run('<p><img src="a.jpg" alt=""></p>').after).toBe('<p><img src="a.jpg" alt=""></p>');
    expect(roundTripper.run('<img src="a.jpg" alt="">').after).toBe('<p><img src="a.jpg" alt=""></p>');
  });

  it('does not add an empty paragraph after a last list', () => {
    expect(roundTripper.run('<ul><li><p>a</p></li></ul>').after).toBe('<ul><li><p>a</p></li></ul>');
  });

  it('does not report adjacent marks merged by the editor', () => {
    expect(lostOf('<p><strong>a</strong><strong>b</strong></p>')).toEqual([]);
  });

  it('reports the inline colors, sizes and indents the schema has no equivalent for', () => {
    expect(lostOf('<p style="margin-left:50px"><span style="color:#f00;font-size:14px">x</span></p>'))
      .toEqual(expect.arrayContaining(['style:margin-left', 'style:color', 'style:font-size']));
  });

  it('reports a flattened <div>', () => {
    const result = roundTripper.run('<div class="box"><ul><li>x</li></ul></div>');

    expect(result.lost.map(({ signal }) => signal)).toEqual(expect.arrayContaining(['tag:div', 'class:box']));
    expect(result.textChanged).toBe(false);
  });

  it('reports a text the editor drops', () => {
    expect(roundTripper.run('<p>a</p><script>alert(1)</script>').textChanged).toBe(true);
  });
});

describe('bin/roundtrip.js', () => {
  const bin = path.resolve(import.meta.dirname, '../bin/roundtrip.js');
  const run = (input) => spawnSync(process.execPath, [bin], { input, encoding: 'utf8' });

  it('echoes the input fields and adds the round trip result', () => {
    const { status, stdout } = run(`${JSON.stringify({ id: 7, field: 'text.text', html: '<p style="color:red">x</p>' })}\n\n`);
    const lines = stdout.trim().split('\n').map((line) => JSON.parse(line));

    expect(status).toBe(0);
    expect(lines).toHaveLength(1);
    expect(lines[0]).toMatchObject({
      id: 7,
      field: 'text.text',
      html: '<p style="color:red">x</p>',
      after: '<p>x</p>',
      textChanged: false,
      lost: [{ signal: 'style:color', before: 1, after: 0 }],
    });
  });

  it('keeps one output line per input line, in order', () => {
    const { stdout } = run(['a', 'b', 'c'].map((id) => JSON.stringify({ id, html: `<p>${id}</p>` })).join('\n'));

    expect(stdout.trim().split('\n').map((line) => JSON.parse(line).id)).toEqual(['a', 'b', 'c']);
  });

  it('aborts with an error on a line which is not JSON', () => {
    const { status, stderr } = run('not json\n');

    expect(status).toBe(1);
    expect(stderr).toContain('Invalid JSON line');
  });
});
