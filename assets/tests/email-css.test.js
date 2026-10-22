import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { buildEmailCss, CONTENT_FILE, EMAIL_FILE } from '../bin/build-email-css.js';

// What the generator writes for a content stylesheet, without the header
const body = (css) => buildEmailCss(css)
  .split('*/\n')
  .slice(1)
  .join('*/\n')
  .trim();

describe('tiptap-email.css', () => {
  it('is generated from tiptap-content.css: run `node bin/build-email-css.js` after changing it', () => {
    expect(readFileSync(EMAIL_FILE, 'utf8')).toBe(buildEmailCss(readFileSync(CONTENT_FILE, 'utf8')));
  });

  it('defines every ut-* class of the content stylesheet', () => {
    const classes = (file) => new Set(readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')
      .match(/\.ut-[a-z0-9-]+/g));
    const email = classes(EMAIL_FILE);

    expect([...classes(CONTENT_FILE)].filter((cls) => !email.has(cls))).toEqual([]);
  });
});

describe('buildEmailCss', () => {
  it('replaces the custom properties by their value, or by their fallback', () => {
    expect(body(':root { --a: #111111; }\n.ut-a { color: var(--a); }\n.ut-b { color: var(--x, var(--a)); }'))
      .toBe('.ut-a { color: #111111; }\n.ut-b { color: #111111; }');
  });

  it('fails on a custom property without a value', () => {
    expect(() => buildEmailCss('.ut-a { color: var(--nope); }')).toThrow('--nope');
  });

  it('converts rem to px and a translucent rgb() to a hex color blended on white', () => {
    expect(body('.ut-a { padding: 0.75rem 1.25rem; background-color: rgb(13 110 253 / 10%); }'))
      .toBe('.ut-a { padding: 12px 20px; background-color: #e7f1ff; }');
  });

  it('writes the logical properties as physical ones, keeping !important', () => {
    expect(body('.ut-a { margin-inline: auto 0 !important; }'))
      .toBe('.ut-a { margin-left: auto !important; margin-right: 0 !important; }');
    expect(body('.ut-b { margin-inline-start: 40px; margin-block: 0; }'))
      .toBe('.ut-b { margin-left: 40px; margin-top: 0; margin-bottom: 0; }');
  });

  it('flattens the nested rules of a ut-* class', () => {
    expect(body('.ut-a { & td,\n  & th { border-style: dotted; }\n  &.x { color: red; } }')).toBe(
      '.ut-a td,\n.ut-a th {\n  border-style: dotted;\n}\n\n.ut-a.x { color: red; }',
    );
  });

  it('leaves out :root and the rules of the content wrapper', () => {
    expect(body(':root { --a: red; }\n:is(.x, .y) { & table { border: 0; } }\n.ut-a { color: red; }'))
      .toBe('.ut-a { color: red; }');
  });

  it('keeps the comment of a group of rules, and only those of the kept ones', () => {
    expect(body('/* Header */\n/* Gone */\n:root { --a: red; }\n/* Kept */\n.ut-a { color: red; }'))
      .toBe('/* Kept */\n.ut-a { color: red; }');
  });
});
