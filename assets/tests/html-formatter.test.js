import { describe, expect, it } from 'vitest';
import HtmlFormatter from '../src/editor/html-formatter.js';

describe('HtmlFormatter.format', () => {
  it('puts each top-level block on its own line', () => {
    expect(HtmlFormatter.format('<h1>Title</h1><p>One</p><p>Two</p>')).toBe('<h1>Title</h1>\n<p>One</p>\n<p>Two</p>');
  });

  it('keeps inline content untouched', () => {
    const html = '<p>a <strong>b</strong> <a href="/x">c</a></p>';

    expect(HtmlFormatter.format(html)).toBe(html);
  });

  it('indents nested blocks', () => {
    expect(HtmlFormatter.format('<ul><li><p>A</p></li><li><p>B</p><ul><li><p>C</p></li></ul></li></ul>')).toBe([
      '<ul>',
      '  <li>',
      '    <p>A</p>',
      '  </li>',
      '  <li>',
      '    <p>B</p>',
      '    <ul>',
      '      <li>',
      '        <p>C</p>',
      '      </li>',
      '    </ul>',
      '  </li>',
      '</ul>',
    ].join('\n'));
  });

  it('keeps an inline-only list item on one line', () => {
    expect(HtmlFormatter.format('<ul><li>A</li></ul>')).toBe('<ul>\n  <li>A</li>\n</ul>');
  });

  it('lays out tables and keeps attributes', () => {
    expect(HtmlFormatter.format('<table class="t"><tbody><tr><td colspan="2"><p>x</p></td></tr></tbody></table>'))
      .toBe([
        '<table class="t">',
        '  <tbody>',
        '    <tr>',
        '      <td colspan="2">',
        '        <p>x</p>',
        '      </td>',
        '    </tr>',
        '  </tbody>',
        '</table>',
      ].join('\n'));
  });

  it('lays out the colgroup of a table', () => {
    const html = '<table><colgroup><col width="50"><col></colgroup><tbody><tr><td><p>x</p></td></tr></tbody></table>';

    expect(HtmlFormatter.format(html))
      .toBe([
        '<table>',
        '  <colgroup>',
        '    <col width="50">',
        '    <col>',
        '  </colgroup>',
        '  <tbody>',
        '    <tr>',
        '      <td>',
        '        <p>x</p>',
        '      </td>',
        '    </tr>',
        '  </tbody>',
        '</table>',
      ].join('\n'));
  });

  it('never touches the whitespace of a pre', () => {
    const html = '<pre><code>a\n  b</code></pre>';

    expect(HtmlFormatter.format(html)).toBe(html);
  });

  it('only adds whitespace between blocks', () => {
    const html = '<blockquote><p>q</p></blockquote><ul><li><p>a <em>b</em></p></li></ul><p>x<br>y</p>';

    expect(HtmlFormatter.format(html).replace(/>\s*\n\s*</g, '><')).toBe(html);
  });

  it('returns an empty content as it is', () => {
    expect(HtmlFormatter.format('')).toBe('');
  });
});
