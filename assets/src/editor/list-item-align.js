/**
 * CKEditor writes the alignment of a list per item, on the `<li>` (`<li style="text-align:justify">`). The schema
 * carries an alignment on the paragraphs, as a class: the one of an item is reported on the paragraphs of its text.
 */

const ALIGNS = ['center', 'right', 'justify'];

// Elements which cannot sit in a paragraph: a run of inline content around them is the text of the item.
const BLOCK_ELEMENTS = /^(address|article|aside|blockquote|div|dl|fieldset|figure|footer|form|h[1-6]|header|hr|ol|p|pre|section|table|ul)$/;

/** The `left` alignment is the default one unless the writing direction is right-to-left. */
export const isLeftToRight = (el) => (el.closest('[dir]')?.getAttribute('dir') ?? 'ltr').trim().toLowerCase() === 'ltr';

/**
 * @param {HTMLElement} li
 * @returns {string|null} The alignment to report on the paragraphs of the item, null when there is none to report
 *   (no alignment, the default one, or a value the schema does not know)
 */
export const listItemAlign = (li) => {
  const align = li.style?.textAlign.trim().toLowerCase();

  if (ALIGNS.includes(align) || ('left' === align && !isLeftToRight(li))) {
    return align;
  }

  return null;
};

const hasOwnAlign = (p) => Boolean(p.style.textAlign) || /(^|\s)ut-text-(left|center|right|justify)(\s|$)/.test(p.className);

/**
 * @param {HTMLElement} li
 * @returns {HTMLElement} The element the content of the item is read from: a copy where every paragraph of the text
 *   has the class of the alignment of the item, unless it has its own. The inline content which is not in a paragraph
 *   (the paragraph the editor creates for it would not carry any attribute) is wrapped in one. The item itself is
 *   returned when there is nothing to report.
 */
export const withItemAlign = (li) => {
  const align = listItemAlign(li);

  if (!align) {
    return li;
  }

  const copy = li.cloneNode(true);
  const doc = li.ownerDocument;
  const className = `ut-text-${align}`;
  let run = [];

  const flush = () => {
    if (run.some((node) => 3 !== node.nodeType || '' !== node.textContent.trim())) {
      const p = doc.createElement('p');

      run[0].before(p);
      p.append(...run);
      p.className = className;
    }

    run = [];
  };

  Array.from(copy.childNodes).forEach((node) => {
    const tag = 1 === node.nodeType ? node.tagName.toLowerCase() : null;

    if (tag && BLOCK_ELEMENTS.test(tag)) {
      flush();

      if ('p' === tag && !hasOwnAlign(node)) {
        node.classList.add(className);
      }
    } else if (3 === node.nodeType || 1 === node.nodeType) {
      run.push(node);
    }
  });
  flush();

  return copy;
};
