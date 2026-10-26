import { createRequire } from 'node:module';
import { describe, expect, it } from 'vitest';
import esmSafelist from '../purgecss-safelist.js';

const require = createRequire(import.meta.url);

describe('purgecss safelist', () => {
  it('is the same list for ES modules and CommonJS consumers', () => {
    const cjsSafelist = require('../purgecss-safelist.cjs');

    expect(esmSafelist).toBe(cjsSafelist);
  });

  it('contains only regular expressions covering the runtime-generated selectors', () => {
    expect(esmSafelist.every((pattern) => pattern instanceof RegExp)).toBe(true);
    expect(esmSafelist.some((pattern) => pattern.test('ProseMirror'))).toBe(true);
    expect(esmSafelist.some((pattern) => pattern.test('ut-banner-info'))).toBe(true);
    expect(esmSafelist.some((pattern) => pattern.test('active'))).toBe(true);
  });
});
