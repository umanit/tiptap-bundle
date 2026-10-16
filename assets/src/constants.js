/** Property under which the modal controller instance is exposed on its `<dialog>` element. */
export const MODAL_PROPERTY = 'umanitTiptapModal';

/**
 * Event dispatched on the root element of an editor to replace its content: `detail.content` holds the HTML.
 *
 * @example
 * editorElement.dispatchEvent(new CustomEvent('umanit-tiptap:set-content', { detail: { content: '<p>Hello</p>' } }));
 */
export const SET_CONTENT_EVENT = 'umanit-tiptap:set-content';
