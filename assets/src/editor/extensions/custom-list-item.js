import { getNodeType } from '@tiptap/core';
import { ListItem } from '@tiptap/extension-list';
import { sinkListItem } from '@tiptap/pm/schema-list';
import { withItemAlign } from '../list-item-align.js';
import { closestList, hasTightClass, TIGHT_LIST_CLASS } from '../tight-list.js';

// Like sinkListItem, whether the previous item already ends with a list of the same kind: the item then joins it
const isSunkIntoExistingList = ($from, type) => {
  for (let { depth } = $from; depth > 0; depth -= 1) {
    if ($from.node(depth).type === type) {
      const list = $from.node(depth - 1);
      const index = $from.index(depth - 1);

      return index > 0 && list.child(index - 1).lastChild?.type === list.type;
    }
  }

  return false;
};

// A list item accepts any block first (heading, paragraph, …), like CKEditor,
// instead of the default "paragraph block*" which forces a leading paragraph.
export default ListItem.extend({
  content: 'block+',

  // The alignment CKEditor writes on the item goes on its paragraphs (see withItemAlign)
  parseHTML() {
    return [{ tag: 'li', contentElement: withItemAlign }];
  },

  addCommands() {
    return {
      // Same as the core command, but a list this creates inside a tight list is tight too
      sinkListItem: (typeOrName) => ({ state, tr, dispatch }) => {
        const type = getNodeType(typeOrName, state.schema);
        const joinsExistingList = isSunkIntoExistingList(state.selection.$from, type);

        if (!sinkListItem(type)(state, dispatch)) {
          return false;
        }

        if (dispatch && !joinsExistingList) {
          const list = closestList(tr.selection.$from);
          const parent = list ? closestList(tr.doc.resolve(list.pos)) : null;

          if (parent && hasTightClass(parent.node) && !hasTightClass(list.node)) {
            const classes = (list.node.attrs.class ?? '').split(' ').filter(Boolean);

            classes.push(TIGHT_LIST_CLASS);
            tr.setNodeMarkup(list.pos, undefined, { ...list.node.attrs, class: classes.join(' ') });
          }
        }

        return true;
      },
    };
  },
});
