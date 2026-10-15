import { Mark } from '@tiptap/core';

export default Mark.create({
  name: 'textColor',

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => element.getAttribute('class'),
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },

  parseHTML() {
    return [{
      tag: 'span',
      getAttrs: (element) => {
        const cls = element.getAttribute('class') || '';
        return /\but-color-\S+/.test(cls) ? null : false;
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
