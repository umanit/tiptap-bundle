import TipTapEditor from '../editor/tiptap-editor.js';
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

  /**
   * @param {Window} [win]
   * @param {import('../editor/tiptap-editor.js').LegacyStyles|null} [legacyStyles] Palette of the legacy import mode
   */
  constructor(win = window, legacyStyles = null) {
    this.#parser = new win.DOMParser();
    this.#editor = new TipTapEditor({}, { element: win.document.createElement('div'), content: '', legacyStyles }, {});
  }

  /**
   * @param {string} html
   * @returns {{ after: string, textChanged: boolean, lost: { signal: string, before: number, after: number }[] }}
   */
  run(html) {
    this.#editor.setContent(html);

    const after = this.#editor.getHTML();
    const before = collectSignals(html, this.#parser);
    const kept = collectSignals(after, this.#parser);

    return {
      after,
      textChanged: before.text !== kept.text,
      lost: lostSignals(before.signals, kept.signals),
    };
  }

  destroy() {
    this.#editor.destroy();
  }
}
