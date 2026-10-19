import { Extension } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';
import { indentClassOf } from '../indent.js';

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
            parseHTML: (element) => {
              const classes = (HtmlCleaner.parseClass(element) ?? '').split(' ').filter(Boolean);
              const indent = indentClassOf(element.style);

              // The indentation another editor wrote inline, unless the content already has its class
              if (indent && !classes.some((cls) => cls.startsWith('ut-indent-'))) {
                classes.push(indent);
              }

              return classes.length > 0 ? classes.join(' ') : null;
            },
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
