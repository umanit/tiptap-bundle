import { describe, expect, it } from 'vitest';
import HtmlCleaner from '../src/editor/html-cleaner.js';

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

  it('keeps a small left margin as is', () => {
    const result = HtmlCleaner.normalizeWordHtml('<p style="margin-left: 40px">Indented</p>');

    expect(result).not.toContain('text-align');
  });

  it('applies the alignment declared in a <style> block', () => {
    const html = '<style>.x1 { color: red; text-align: center }</style><p class="x1 MsoNormal">Centered</p>';

    expect(HtmlCleaner.normalizeWordHtml(html)).toContain('text-align: center');
  });

  it('does not override an inline alignment', () => {
    const html = '<style>.x1 { text-align: center }</style><p class="x1 MsoNormal" style="text-align: left">T</p>';

    expect(HtmlCleaner.normalizeWordHtml(html)).toContain('text-align: left');
  });
});
