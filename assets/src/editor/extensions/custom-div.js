import { Node } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';

/**
 * A `<div>` with a class: a block of the site (layout, callout…) which the editor keeps around its content. It has no
 * button in the toolbar, it is written in the source view. A `<div>` without a class (what Word and CKEditor wrap
 * the text in) is not read: its content is kept, the wrapper is not.
 */
export default Node.create({
  name: 'div',
  group: 'block',
  content: 'block+',
  defining: true,

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => HtmlCleaner.parseClass(element),
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },

  parseHTML() {
    return [{
      tag: 'div',
      getAttrs: (element) => (HtmlCleaner.parseClass(element) ? null : false),
    }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', HTMLAttributes, 0];
  },
});
