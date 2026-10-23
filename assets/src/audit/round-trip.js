import TipTapEditor from '../editor/tiptap-editor.js';
import collectInlineStyles from './inline-styles.js';
import { collectSignals, lostSignals } from './signals.js';

/**
 * Round-trips HTML contents through the editor schema, as the editor does when a content is loaded and saved,
 * and reports what the schema dropped.
 *
 * Needs a DOM (`window`/`document` globals) because the editor does.
 */
export default class RoundTripper {
  #editor;
  #parser;
  #legacy;

  /**
   * @param {Window} [win]
   * @param {import('../editor/tiptap-editor.js').LegacyStyles|null} [legacyStyles] Palette of the legacy import mode
   */
  constructor(win = window, legacyStyles = null) {
    this.#parser = new win.DOMParser();
    this.#legacy = legacyStyles !== null;
    // No trailing paragraph: it helps to type after a last list, not to rewrite a content
    this.#editor = new TipTapEditor(
      {},
      {
        element: win.document.createElement('div'), content: '', legacyStyles, trailingNode: false,
      },
      {},
    );
  }

  /**
   * @param {string} html
   * @returns {{
   *   after: string,
   *   textChanged: boolean,
   *   lost: { signal: string, before: number, after: number }[],
   *   inlineStyles: { color: string[], backgroundColor: string[], fontSize: string[] },
   * }}
   */
  run(html) {
    this.#editor.setContent(html);

    const after = this.#editor.getHTML();
    const kept = collectSignals(after, this.#parser);
    // The identifiers of the produced content tell which named anchors are still reachable
    const before = collectSignals(html, this.#parser, { convertCellWidths: this.#legacy, producedIds: kept.ids });

    return {
      after,
      textChanged: before.text !== kept.text,
      lost: lostSignals(before.signals, kept.signals),
      inlineStyles: collectInlineStyles(html, this.#parser),
    };
  }

  destroy() {
    this.#editor.destroy();
  }
}
