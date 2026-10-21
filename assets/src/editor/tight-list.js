/**
 * CKEditor writes a list item as `<li>text</li>`, the schema of the editor as `<li><p>text</p></li>`, and the margin
 * of the paragraph then spaces the items out. A list whose items were all written bare keeps its look with this class,
 * which cancels the margin of those paragraphs.
 */
export const TIGHT_LIST_CLASS = 'ut-list-tight';

const BLOCK_TAGS = /^(p|h[1-6]|ul|ol|blockquote|pre|table|div|hr)$/i;

// An item is bare when what it starts with is not a block: its text or inline content is written directly in it.
const isBareItem = (item) => {
  const first = Array.from(item.childNodes).find((node) => node.nodeType === 1 || node.textContent.trim() !== '');

  return first !== undefined && !(first.nodeType === 1 && BLOCK_TAGS.test(first.tagName));
};

/**
 * @param {Element} element
 * @returns {boolean} Whether it is a list whose items are all bare
 */
export const isTightList = (element) => {
  if (!/^(ul|ol)$/i.test(element.tagName)) {
    return false;
  }

  const items = Array.from(element.children).filter((child) => child.tagName === 'LI');

  return items.length > 0 && items.every(isBareItem);
};

const LIST_TYPES = ['bulletList', 'orderedList'];

/**
 * @param {import('@tiptap/pm/model').ResolvedPos} $pos
 * @returns {{ node: import('@tiptap/pm/model').Node, pos: number }|null} The innermost list around the position
 */
export const closestList = ($pos) => {
  for (let { depth } = $pos; depth > 0; depth -= 1) {
    const node = $pos.node(depth);

    if (LIST_TYPES.includes(node.type.name)) {
      return { node, pos: $pos.before(depth) };
    }
  }

  return null;
};

/**
 * @param {import('@tiptap/pm/model').Node} node
 * @returns {boolean}
 */
export const hasTightClass = (node) => (node.attrs.class ?? '').split(' ').includes(TIGHT_LIST_CLASS);
