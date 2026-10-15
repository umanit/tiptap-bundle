import { Mark } from '@tiptap/core';

export default Mark.create({
  name: 'textBgColor',

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => {
          const cls = element.getAttribute('class') || '';
          const match = cls.match(/\but-bg-\S+/);
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
        return /\but-bg-\S+/.test(cls) ? null : false;
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
