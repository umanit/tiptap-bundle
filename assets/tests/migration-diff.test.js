import { describe, expect, it } from 'vitest';
import { analyzeContent, diffLines } from '../src/editor/migration-diff.js';

const parser = new DOMParser();

describe('diffLines', () => {
  it('marks the lines which changed, with their context', () => {
    const lines = diffLines('<p>a</p><p class="x">b</p><p>c</p>', '<p>a</p><p>b</p><p>c</p>');

    expect(lines).toEqual([
      { type: 'same', line: '<p>a</p>' },
      { type: 'removed', line: '<p class="x">b</p>' },
      { type: 'added', line: '<p>b</p>' },
      { type: 'same', line: '<p>c</p>' },
    ]);
  });

  it('folds the unchanged lines far from a change', () => {
    const body = Array.from({ length: 10 }, (_, i) => `<p>${i}</p>`).join('');
    const lines = diffLines(`${body}<p>old</p>`, `${body}<p>new</p>`, 1);

    expect(lines.map(({ type }) => type)).toEqual(['gap', 'same', 'removed', 'added']);
  });
});

describe('analyzeContent', () => {
  it('reports nothing for a content the editor keeps as is', () => {
    const result = analyzeContent('<p>Hello</p>\n<p>World</p>', '<p>Hello</p><p>World</p>', parser);

    expect(result).toMatchObject({ changed: false, lost: [], lines: [] });
  });

  it('ignores the notation: non-breaking spaces, order of the attributes and of the classes', () => {
    const result = analyzeContent(
      '<p class="b a">Un\u00a0texte</p><img src="a.png" alt="x">',
      '<p class="a b">Un&nbsp;texte</p><img alt="x" src="a.png">',
      parser,
    );

    expect(result.changed).toBe(false);
  });

  it('ignores the white space the editor does not keep', () => {
    const result = analyzeContent(
      '<p>\n  Intro<br>\n&nbsp;</p>\n<p>\n<br>\nSuite   du  texte </p>',
      '<p>Intro<br>&nbsp;</p><p><br>Suite du texte</p>',
      parser,
    );

    expect(result.changed).toBe(false);
  });

  it('ignores the size and the float of an image, written as a style or as what the editor keeps', () => {
    const result = analyzeContent(
      '<p><img src="a.png" alt="" style="height:403px;width:783px;float:left;margin:10px"></p>',
      '<p><img alt="" class="ut-float-start ut-ma-10" height="403" src="a.png" width="783"></p>',
      parser,
    );

    expect(result.changed).toBe(false);
  });

  it('reports a size in another unit than pixels, which the editor does not keep', () => {
    const result = analyzeContent('<p><img src="a.png" style="width:50%"></p>', '<p><img src="a.png"></p>', parser);

    expect(result.changed).toBe(true);
  });

  it('keeps the white space which separates words', () => {
    const result = analyzeContent(
      '<p>un <strong>deux</strong> trois</p>',
      '<p>un<strong>deux</strong>trois</p>',
      parser,
    );

    expect(result.changed).toBe(true);
  });

  it('still reports a real change of an attribute', () => {
    const result = analyzeContent('<img src="a.png" alt="x">', '<img alt="y" src="a.png">', parser);

    expect(result.changed).toBe(true);
  });

  it('reports a rewrite without loss', () => {
    const result = analyzeContent('<p><b>Hello</b></p>', '<p><strong>Hello</strong></p>', parser);

    expect(result.changed).toBe(true);
    expect(result.lost).toEqual([]);
    expect(result.lines.some(({ type }) => type === 'added')).toBe(true);
  });

  it('reports what the editor dropped', () => {
    const result = analyzeContent('<div class="box"><p>x</p></div>', '<p>x</p>', parser);

    expect(result.lost.map(({ signal }) => signal)).toEqual(['tag:div', 'class:box']);
  });

  it('ignores the paragraph the editor adds after a last list', () => {
    const result = analyzeContent('<ul><li><p>x</p></li></ul>', '<ul><li><p>x</p></li></ul><p></p>', parser);

    expect(result.changed).toBe(false);
  });
});
