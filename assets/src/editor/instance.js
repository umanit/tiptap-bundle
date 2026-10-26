import { EDITOR_PROPERTY } from '../constants.js';

/**
 * Finds the editor an element belongs to: the element is the root of the editor, or inside it (the textarea, a button
 * of the toolbar…).
 *
 * The editor is available once its controller has connected: listen to `umanit-tiptap:ready` (`READY_EVENT`) to be
 * told, when your code may run before.
 *
 * @param {Element|null} element
 *
 * @returns {import('./tiptap-editor.js').default|null}
 */
export function getTipTapEditor(element) {
  for (let current = element; current; current = current.parentElement) {
    if (current[EDITOR_PROPERTY]) {
      return current[EDITOR_PROPERTY];
    }
  }

  return null;
}
