import { Extension } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';

export default Extension.create({
  name: 'customBlockClasses',
  addGlobalAttributes() {
    return [
      {
        types: ['paragraph', 'heading'],
        attributes: {
          class: {
            default: null,
            renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
            parseHTML: (element) => HtmlCleaner.parseClass(element),
          },
        },
      },
    ];
  },

  addCommands() {
    return {
      setBlockClass: (className) => ({ commands, editor }) => {
        if (editor.isActive('heading')) {
          return commands.updateAttributes('heading', { class: className });
        }

        return commands.updateAttributes('paragraph', { class: className });
      },
    };
  },
});
