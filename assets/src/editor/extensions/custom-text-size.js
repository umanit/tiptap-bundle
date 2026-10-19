import { Mark } from '@tiptap/core';
import { resolveLegacyClass } from '../legacy-styles.js';

export default Mark.create({
  name: 'textSize',

  addOptions() {
    return { legacyStyles: null };
  },

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => {
          const match = element.getAttribute('class')?.match(/\but-fs-\d+/);
          return match ? match[0] : resolveLegacyClass(this.options.legacyStyles, 'fontSize', element);
        },
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },

  parseHTML() {
    return [{
      tag: 'span',
      // A span may carry several of these marks (color, background, size): let the other rules read it too.
      consuming: false,
      getAttrs: (element) => {
        const cls = element.getAttribute('class') || '';
        return /\but-fs-\d+/.test(cls) || resolveLegacyClass(this.options.legacyStyles, 'fontSize', element)
          ? null
          : false;
      },
    }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['span', HTMLAttributes, 0];
  },

  addCommands() {
    return {
      setTextSize: (className) => ({ commands }) => {
        if (!className) {
          return commands.unsetMark(this.name);
        }

        return commands.setMark(this.name, { class: className });
      },
      unsetTextSize: () => ({ commands }) => commands.unsetMark(this.name),
    };
  },
});
