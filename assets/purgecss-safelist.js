import { createRequire } from 'node:module';

/**
 * ES module entry of `purgecss-safelist.cjs`, the single source of the list.
 *
 * @example
 * import tiptapSafelist from '@umanit/tiptap-bundle/purgecss-safelist';
 *
 * new PurgeCSSPlugin({ safelist: { standard: [...tiptapSafelist] } });
 */
export default createRequire(import.meta.url)('./purgecss-safelist.cjs');
