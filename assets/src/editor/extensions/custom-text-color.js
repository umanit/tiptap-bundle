import { Mark } from '@tiptap/core';
import { resolveLegacyClass } from '../legacy-styles.js';

export default Mark.create({
  name: 'textColor',

  addOptions() {
    return { legacyStyles: null };
  },

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => {
          const cls = element.getAttribute('class') || '';

          return /\but-color-\S+/.test(cls) ? cls : resolveLegacyClass(this.options.legacyStyles, 'color', element);
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
        return /\but-color-\S+/.test(cls) || resolveLegacyClass(this.options.legacyStyles, 'color', element) ? null : false;
      },
    }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['span', HTMLAttributes, 0];
  },

  addCommands() {
    return {
      setTextColor: (className) => ({ commands }) => {
        if (!className) {
          return commands.unsetMark(this.name);
        }

        return commands.setMark(this.name, { class: className });
      },
      unsetTextColor: () => ({ commands }) => commands.unsetMark(this.name),
    };
  },
});
