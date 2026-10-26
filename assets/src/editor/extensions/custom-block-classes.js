import { Extension } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';
import { marginClassesOf } from '../indent.js';
import { closestList, hasTightClass, isTightList, TIGHT_LIST_CLASS } from '../tight-list.js';

/**
 * @param {import('@tiptap/pm/state').EditorState} state
 * @param {string} className
 * @returns {boolean} Whether the text block at the cursor (the start of the selection) has the class
 */
export const hasBlockClass = (state, className) => (state.selection.$from.parent.attrs.class ?? '')
  .split(' ')
  .includes(className);

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

              if (isTightList(element) && !classes.includes(TIGHT_LIST_CLASS)) {
                classes.push(TIGHT_LIST_CLASS);
              }

              return classes.length > 0 ? classes.join(' ') : null;
            },
          },
        },
      },
      {
        // Kept as they are (the site's own classes): no margins nor tightness, which are about the text blocks.
        // `code` is a mark which excludes the others, so its class is one of its attributes rather than a span.
        types: ['listItem', 'blockquote', 'codeBlock', 'horizontalRule', 'code'],
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

      // Spaces the items of the closest list out (default) or packs them, by the class which cancels the margin of
      // their paragraphs.
      toggleListTight: () => ({ state, tr, dispatch }) => {
        const list = closestList(state.selection.$from);

        if (!list) {
          return false;
        }

        if (dispatch) {
          const classes = (list.node.attrs.class ?? '').split(' ').filter(Boolean);
          const next = hasTightClass(list.node)
            ? classes.filter((cls) => cls !== TIGHT_LIST_CLASS)
            : [...classes, TIGHT_LIST_CLASS];

          tr.setNodeMarkup(list.pos, undefined, { ...list.node.attrs, class: next.length > 0 ? next.join(' ') : null });
          dispatch(tr);
        }

        return true;
      },
    };
  },
});
