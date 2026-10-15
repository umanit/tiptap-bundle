/**
 * Selectors generated at runtime by TipTap/ProseMirror or only present in the content stored in database: PurgeCSS
 * cannot see them in your templates and would drop their styles in production.
 *
 * @example
 * import tiptapSafelist from '@umanit/tiptap-bundle/purgecss-safelist';
 *
 * new PurgeCSSPlugin({ safelist: { standard: [...tiptapSafelist] } });
 */
export default [
  /ProseMirror/,
  /tiptap/,
  /is-editor-empty/,
  /selectedCell/,
  /column-resize-handle/,
  /^ut-/,
];
