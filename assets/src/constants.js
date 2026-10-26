/** Property under which the modal controller instance is exposed on its `<dialog>` element. */
export const MODAL_PROPERTY = 'umanitTiptapModal';

/** Property under which the editor (a `TipTapEditor`) is exposed on the root element of its controller. */
export const EDITOR_PROPERTY = 'umanitTiptapEditor';

/**
 * Event dispatched on the root element of an editor once it is created: `detail.editor` is the `TipTapEditor`.
 * It bubbles, so it can be listened to on `document`.
 *
 * @example
 * document.addEventListener('umanit-tiptap:ready', ({ detail }) => detail.editor.getEditor().commands.focus());
 */
export const READY_EVENT = 'umanit-tiptap:ready';

/**
 * Event dispatched on the root element of an editor to replace its content: `detail.content` holds the HTML.
 *
 * @example
 * editorElement.dispatchEvent(new CustomEvent('umanit-tiptap:set-content', { detail: { content: '<p>Hello</p>' } }));
 */
export const SET_CONTENT_EVENT = 'umanit-tiptap:set-content';
