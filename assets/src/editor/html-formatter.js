/**
 * HtmlFormatter
 *
 * Lays out the compact HTML written by the editor for reading: one block per line, nested blocks indented.
 * Only the whitespace between blocks is added, so the content is unchanged for the editor and the browsers.
 */
const CONTAINER_TAGS = new Set([
  'blockquote', 'ul', 'ol', 'li', 'table', 'colgroup', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'div',
]);
const BLOCK_TAGS = new Set([...CONTAINER_TAGS, 'col', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'pre']);
const INDENT = '  ';

const isBlock = (node) => node.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has(node.localName);

/**
 * A container is spread on several lines only when it holds blocks and nothing else: any text or inline content
 * keeps it as it is, because whitespace would be visible there.
 */
const isSpread = (element) => CONTAINER_TAGS.has(element.localName)
  && element.childNodes.length > 0
  && [...element.childNodes].every(isBlock);

const formatNode = (node, depth) => {
  if (node.nodeType !== Node.ELEMENT_NODE) {
    return [`${INDENT.repeat(depth)}${node.textContent}`];
  }

  if (!isSpread(node)) {
    return [`${INDENT.repeat(depth)}${node.outerHTML}`];
  }

  const shell = node.cloneNode(false).outerHTML;
  const closing = `</${node.localName}>`;
  const opening = shell.slice(0, shell.length - closing.length);
  const indent = INDENT.repeat(depth);

  return [
    `${indent}${opening}`,
    ...[...node.childNodes].flatMap((child) => formatNode(child, depth + 1)),
    `${indent}${closing}`,
  ];
};

const HtmlFormatter = {
  /**
   * @param {string} html Compact HTML, as the editor writes it
   * @returns {string}
   */
  format(html) {
    if (!html) {
      return html;
    }

    const template = document.createElement('template');
    template.innerHTML = html;

    return [...template.content.childNodes].flatMap((node) => formatNode(node, 0)).join('\n');
  },
};

export default HtmlFormatter;
