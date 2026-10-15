import { Mark } from '@tiptap/core';

export default Mark.create({
  name: 'textSize',

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => {
          const match = element.getAttribute('class')?.match(/\but-fs-\d+/);
          return match ? match[0] : null;
        },
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },

  parseHTML() {
    return [{
      tag: 'span',
      getAttrs: (element) => {
        const cls = element.getAttribute('class') || '';
        return /\but-fs-\d+/.test(cls) ? null : false;
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
