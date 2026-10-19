import { Mark } from '@tiptap/core';
import { resolveLegacyClass } from '../legacy-styles.js';

export default Mark.create({
  name: 'textBgColor',

  addOptions() {
    return { legacyStyles: null };
  },

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => {
          const cls = element.getAttribute('class') || '';
          const match = cls.match(/\but-bg-\S+/);
          return match ? match[0] : resolveLegacyClass(this.options.legacyStyles, 'backgroundColor', element);
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
        return /\but-bg-\S+/.test(cls) || resolveLegacyClass(this.options.legacyStyles, 'backgroundColor', element)
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
      setTextBgColor: (className) => ({ commands }) => {
        if (!className) {
          return commands.unsetMark(this.name);
        }

        return commands.setMark(this.name, { class: className });
      },
      unsetTextBgColor: () => ({ commands }) => commands.unsetMark(this.name),
    };
  },
});
