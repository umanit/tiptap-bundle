import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import HtmlCleaner, { RIGHT_ALIGN_MARGIN, leftMarginOf } from '../src/editor/html-cleaner.js';

// Replayed by `tests/Unit/Cleaning/WordClassesRuleTest.php` on the PHP side: both drop the same classes of Word
const sharedClasses = JSON.parse(readFileSync(
  path.resolve(import.meta.dirname, '../../tests/Fixtures/Cleaning/word-classes-cases.json'),
  'utf8',
));

describe('HtmlCleaner.parseClass', () => {
  const element = (cls) => {
    const el = document.createElement('p');
    if (cls !== null) {
      el.setAttribute('class', cls);
    }

    return el;
  };

  it('returns null without class', () => {
    expect(HtmlCleaner.parseClass(element(null))).toBeNull();
  });

  it('keeps meaningful classes', () => {
    expect(HtmlCleaner.parseClass(element('ut-banner-info foo'))).toBe('ut-banner-info foo');
  });

  it('drops Word, Google Docs and hash classes', () => {
    expect(HtmlCleaner.parseClass(element('MsoNormal SCXW123 kix-foo abcdefghijklmnopqrstu keep'))).toBe('keep');
  });

  it('returns null when only noise remains', () => {
    expect(HtmlCleaner.parseClass(element('MsoNormal'))).toBeNull();
  });

  it.each(sharedClasses.cases.map((c) => [c.classes, c.kept]))(
    'drops the classes of Word as the cleaning of the database does: "%s"',
    (classes, kept) => {
      expect(HtmlCleaner.parseClass(element(classes))).toBe(kept);
    },
  );
});

describe('leftMarginOf', () => {
  it('reads margin-left', () => {
    expect(leftMarginOf('color: red; margin-left: 12.5px')).toBe(12.5);
  });

  it('reads the fourth value of a four-values margin', () => {
    expect(leftMarginOf('margin: 0px 1px 2px 300px')).toBe(300);
  });

  it('does not take the other shorthands for a left margin, as both sides have the same one', () => {
    expect(leftMarginOf('margin: 0 300px')).toBe(0);
    expect(leftMarginOf('margin: 0 300px 0')).toBe(0);
  });

  it('ignores the other units and the other properties', () => {
    expect(leftMarginOf('margin-left: 3em')).toBe(0);
    expect(leftMarginOf('padding-left: 300px')).toBe(0);
  });
});

describe('HtmlCleaner.normalizeWordHtml', () => {
  it('returns empty values untouched', () => {
    expect(HtmlCleaner.normalizeWordHtml('')).toBe('');
    expect(HtmlCleaner.normalizeWordHtml(null)).toBeNull();
  });

  it('returns clean HTML untouched', () => {
    const html = '<p>Hello</p>';

    expect(HtmlCleaner.normalizeWordHtml(html)).toBe(html);
  });

  it('turns a huge left margin into a right alignment', () => {
    const result = HtmlCleaner.normalizeWordHtml('<p style="margin-left: 400px">Right</p>');

    expect(result).toContain('text-align: right');
  });

  it('only takes a margin past the threshold for an alignment', () => {
    const at = HtmlCleaner.normalizeWordHtml(`<p style="margin-left: ${RIGHT_ALIGN_MARGIN}px">T</p>`);
    const past = HtmlCleaner.normalizeWordHtml(`<p style="margin-left: ${RIGHT_ALIGN_MARGIN + 1}px">T</p>`);

    expect(at).not.toContain('text-align');
    expect(past).toContain('text-align: right');
    expect(past).not.toContain('margin-left');
  });

  it('reads the margin of a four-values shorthand', () => {
    expect(HtmlCleaner.normalizeWordHtml('<p style="margin: 0px 0px 0px 400px">T</p>')).toContain('text-align: right');
  });

  it('keeps a small left margin as is', () => {
    const result = HtmlCleaner.normalizeWordHtml('<p style="margin-left: 40px">Indented</p>');

    expect(result).not.toContain('text-align');
  });

  it('applies the alignment declared in a <style> block', () => {
    const html = '<style>.x1 { color: red; text-align: center }</style><p class="x1 MsoNormal">Centered</p>';

    expect(HtmlCleaner.normalizeWordHtml(html)).toContain('text-align: center');
  });

  it('reads a <style> block whatever its attributes and case', () => {
    const html = '<STYLE type="text/css">.x1 { text-align: right }</STYLE><p class="x1">T</p>';

    expect(HtmlCleaner.normalizeWordHtml(html)).toContain('text-align: right');
  });

  it('applies several classes of a <style> block', () => {
    const html = '<style>.a { text-align: center } .b { text-align: right }</style>'
      + '<p class="a">1</p><p class="b">2</p>';
    const result = HtmlCleaner.normalizeWordHtml(html);

    expect(result).toContain('class="a" style="text-align: center;"');
    expect(result).toContain('class="b" style="text-align: right;"');
  });

  it('does not override an inline alignment', () => {
    const html = '<style>.x1 { text-align: center }</style><p class="x1 MsoNormal" style="text-align: left">T</p>';

    expect(HtmlCleaner.normalizeWordHtml(html)).toContain('text-align: left');
  });
});
