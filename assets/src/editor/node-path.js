const NODE_TAGS = {
  paragraph: 'p',
  bulletList: 'ul',
  orderedList: 'ol',
  listItem: 'li',
  codeBlock: 'pre',
  horizontalRule: 'hr',
  hardBreak: 'br',
  tableRow: 'tr',
  tableCell: 'td',
  tableHeader: 'th',
};

const MARK_TAGS = {
  bold: 'strong',
  italic: 'em',
  strike: 's',
  underline: 'u',
  link: 'a',
  subscript: 'sub',
  superscript: 'sup',
};

/**
 * @param {import('@tiptap/pm/model').Node} node
 * @returns {string}
 */
const nodeTag = (node) => {
  if ('heading' === node.type.name) {
    return `h${node.attrs.level}`;
  }

  return NODE_TAGS[node.type.name] ?? node.type.name;
};

/**
 * The elements around the cursor, from the outermost to the innermost, named after their HTML tag.
 *
 * @param {import('@tiptap/pm/state').EditorState} state
 * @returns {string[]}
 */
export const getNodePath = (state) => {
  const { $from } = state.selection;
  const path = [];

  for (let depth = 1; depth <= $from.depth; depth += 1) {
    path.push(nodeTag($from.node(depth)));
  }

  // A selected node (an image, a rule) is not a parent of the selection but is the element being edited
  if (state.selection.node) {
    path.push(nodeTag(state.selection.node));
  }

  // The marks are not elements of the document tree: they are the inline wrappers of the text at the cursor
  $from.marks().forEach((mark) => path.push(MARK_TAGS[mark.type.name] ?? mark.type.name));

  return path;
};
