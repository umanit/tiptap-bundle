/**
 * Selectors generated at runtime by TipTap/ProseMirror or only present in the content stored in database: PurgeCSS
 * cannot see them in your templates and would drop their styles in production.
 *
 * CommonJS so that it can be required from a `webpack.config.js` as well as imported from an ES module.
 *
 * @example
 * const tiptapSafelist = require('@umanit/tiptap-bundle/purgecss-safelist');
 *
 * new PurgeCSSPlugin({ safelist: { standard: [...tiptapSafelist] } });
 */
module.exports = [
  /ProseMirror/,
  /tiptap/,
  /is-editor-empty/,
  /selectedCell/,
  /column-resize-handle/,
  /^ut-/,
];
