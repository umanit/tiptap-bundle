/**
 * What the editor made of a content when it loaded it: the same comparison as the round-trip CLI, run in the browser
 * to help to check a migration field by field.
 */
import { collectSignals, lostSignals } from '../audit/signals.js';
import imageSize from './image-size.js';
import imageStyle from './image-style.js';

// Beyond this number of cells in the comparison table, the fragments are shown whole, one after the other.
const MAX_CELLS = 4000000;

// The editor appends an empty paragraph after a last list or table, to be able to type after it.
const TRAILING_PARAGRAPH = /<p><\/p>$/;

// HTML white space: unlike `\s`, it leaves the non-breaking space alone, which is a character like any other.
const COLLAPSIBLE = /[ \t\r\n\f]+/g;

const BLOCK_ELEMENTS = /^(p|h[1-6]|ul|ol|li|div|blockquote|pre|hr|table|thead|tbody|tr|td|th)$/;

// Where a line of text begins and ends: the browser does not render the white space there.
const isLineBoundary = (sibling) => !sibling || sibling.nodeName === 'BR' || (sibling.nodeType === 1
  && BLOCK_ELEMENTS.test(sibling.nodeName.toLowerCase()));

// Runs of white space render as one space, and not at all at the edges of a line: the editor does not keep them.
const collapseWhiteSpace = (body) => {
  const walker = body.ownerDocument.createTreeWalker(body, 4 /* NodeFilter.SHOW_TEXT */);
  const texts = [];

  while (walker.nextNode()) {
    if (!walker.currentNode.parentElement?.closest('pre, textarea')) {
      texts.push(walker.currentNode);
    }
  }

  texts.forEach((node) => {
    let text = node.textContent.replace(COLLAPSIBLE, ' ');

    if (isLineBoundary(node.previousSibling)) {
      text = text.replace(/^ /, '');
    }

    if (isLineBoundary(node.nextSibling)) {
      text = text.replace(/ $/, '');
    }

    if (text === '') {
      node.remove();
    } else {
      node.textContent = text;
    }
  });
};

// What the editor keeps of an image as attributes and classes, whether it was written as such or as an inline style.
const normalizeImage = (img) => {
  ['width', 'height'].forEach((name) => {
    const size = imageSize(img.getAttribute(name)) ?? imageSize(img.style.getPropertyValue(name));

    if (size !== null) {
      img.setAttribute(name, size);
      img.style.removeProperty(name);
    }
  });

  const { classes, properties } = imageStyle(img.style);

  if (classes.length > 0) {
    img.className = [...img.classList, ...classes].join(' ');
    properties.forEach((property) => img.style.removeProperty(property));
  }

  if (img.getAttribute('style')?.trim() === '') {
    img.removeAttribute('style');
  }
};

/**
 * Serializes an HTML fragment the same way whatever the way it was written, so that the differences which are only
 * notation or white space do not count: a non-breaking space or `&nbsp;`, the order of the attributes and of the
 * classes, quotes, line breaks and indentation, the size of an image as an attribute or as a style...
 *
 * @param {string} html
 * @param {DOMParser} parser
 * @returns {string}
 */
export const normalizeHtml = (html, parser) => {
  const { body } = parser.parseFromString(html, 'text/html');

  collapseWhiteSpace(body);
  body.querySelectorAll('img').forEach(normalizeImage);

  body.querySelectorAll('*').forEach((el) => {
    const attributes = el.getAttributeNames().sort().map((name) => {
      const value = name === 'class' ? el.getAttribute(name).split(/\s+/).filter(Boolean).sort()
        .join(' ') : el.getAttribute(name);
      el.removeAttribute(name);

      return [name, value];
    });

    attributes.forEach(([name, value]) => el.setAttribute(name, value));
  });

  return body.innerHTML;
};

// One tag per line, so that a change of attribute shows up where it is.
const split = (html) => html.trim().replace(/>\s*</g, '>\n<').split('\n')
  .map((line) => line.trim())
  .filter((line) => line !== '');

const compare = (oldLines, newLines) => {
  const width = newLines.length + 1;
  // Length of the longest common run of lines, from the end of both
  const lengths = new Uint32Array((oldLines.length + 1) * width);

  for (let i = oldLines.length - 1; i >= 0; i -= 1) {
    for (let j = newLines.length - 1; j >= 0; j -= 1) {
      lengths[i * width + j] = oldLines[i] === newLines[j]
        ? lengths[(i + 1) * width + j + 1] + 1
        : Math.max(lengths[(i + 1) * width + j], lengths[i * width + j + 1]);
    }
  }

  const lines = [];
  let i = 0;
  let j = 0;

  while (i < oldLines.length && j < newLines.length) {
    if (oldLines[i] === newLines[j]) {
      lines.push({ type: 'same', line: oldLines[i] });
      i += 1;
      j += 1;
    } else if (lengths[(i + 1) * width + j] >= lengths[i * width + j + 1]) {
      lines.push({ type: 'removed', line: oldLines[i] });
      i += 1;
    } else {
      lines.push({ type: 'added', line: newLines[j] });
      j += 1;
    }
  }

  oldLines.slice(i).forEach((line) => lines.push({ type: 'removed', line }));
  newLines.slice(j).forEach((line) => lines.push({ type: 'added', line }));

  return lines;
};

// Keeps the unchanged lines around a change only, the others become a single `gap` line.
const crop = (lines, context) => {
  const kept = lines.map((entry, index) => entry.type !== 'same'
    || lines.slice(Math.max(0, index - context), index + context + 1).some(({ type }) => type !== 'same'));

  return lines.reduce((cropped, entry, index) => {
    if (kept[index]) {
      cropped.push(entry);
    } else if (cropped.at(-1)?.type !== 'gap') {
      cropped.push({ type: 'gap', line: '…' });
    }

    return cropped;
  }, []);
};

/**
 * @param {string} before
 * @param {string} after
 * @param {number} [context] Unchanged lines kept around a change
 * @returns {{ type: 'same'|'removed'|'added'|'gap', line: string }[]}
 */
export const diffLines = (before, after, context = 2) => {
  const oldLines = split(before);
  const newLines = split(after);

  if (oldLines.length * newLines.length > MAX_CELLS) {
    return [
      ...oldLines.map((line) => ({ type: 'removed', line })),
      ...newLines.map((line) => ({ type: 'added', line })),
    ];
  }

  return crop(compare(oldLines, newLines), context);
};

/**
 * @param {string} before HTML as it was stored
 * @param {string} after HTML as the editor renders it
 * @param {DOMParser} parser
 *
 * The comparison, and the lines of the diff, are made on the normalized HTML (see `normalizeHtml`).
 * @returns {{
 *   changed: boolean,
 *   after: string,
 *   lost: { signal: string, before: number, after: number }[],
 *   lines: { type: 'same'|'removed'|'added'|'gap', line: string }[],
 * }}
 */
export const analyzeContent = (before, after, parser) => {
  const rendered = TRAILING_PARAGRAPH.test(before.trim()) ? after : after.replace(TRAILING_PARAGRAPH, '');
  const [stored, edited] = [before, rendered].map((html) => normalizeHtml(html, parser));
  const changed = split(stored).join('\n') !== split(edited).join('\n');

  return {
    changed,
    after: rendered,
    lost: changed
      ? lostSignals(collectSignals(before, parser).signals, collectSignals(rendered, parser).signals)
      : [],
    lines: changed ? diffLines(stored, edited) : [],
  };
};
