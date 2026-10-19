import { Extension } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';
import { marginClassesOf } from '../indent.js';

export default Extension.create({
  name: 'customBlockClasses',
  addGlobalAttributes() {
    return [
      {
        types: ['paragraph', 'heading', 'bulletList', 'orderedList'],
        attributes: {
          class: {
            default: null,
            renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
            parseHTML: (element) => {
              const classes = (HtmlCleaner.parseClass(element) ?? '').split(' ').filter(Boolean);
              // The margins another editor wrote inline, unless the content already has its own indentation class
              const margins = marginClassesOf(element.style).classes
                .filter((cls) => !classes.some((existing) => existing.startsWith(cls.replace(/\d+$/, ''))));

              classes.push(...margins);

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
