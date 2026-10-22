import { Mark } from '@tiptap/core';
import { INLINE_STYLE_TAGS, resolveLegacyClass } from '../legacy-styles.js';

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
      // CKEditor may put the style on a formatting element (`<strong style="font-size:10pt">`) rather than on a span.
      // An element may carry several of these marks (color, background, size): let the other rules read it too.
      tag: INLINE_STYLE_TAGS,
      // Read before the rule of the formatting element (bold…) which consumes it, or this one is never reached.
      priority: 60,
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
