#!/usr/bin/env node
/**
 * Writes `dist/css/tiptap-email.css` from `dist/css/tiptap-content.css`: the two define the same `ut-*` classes, and
 * only the first one is written by hand.
 *
 * The CSS inliners which prepare the emails (tijsverkoyen/css-to-inline-styles) read plain CSS only. So the generated
 * file keeps the rules of the `ut-*` classes, flattened (no nesting) and with
 *   - `var(--x)` replaced by the value of the `:root` property (or by its fallback),
 *   - `rem` converted to `px`,
 *   - `rgb(r g b / a)` converted to a hex color, blended on white,
 *   - the logical properties (`margin-inline`, `margin-block`…) written as physical ones.
 * The rules of the content wrapper (typography, tables…) and `:root` are not part of it: an email client has no use
 * of them, and cell padding or table borders are set with the `cellpadding` and `border` attributes there.
 *
 * Usage: `node bin/build-email-css.js` (writes the file), `--check` only tells whether it is up to date.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
export const CONTENT_FILE = path.join(HERE, '../dist/css/tiptap-content.css');
export const EMAIL_FILE = path.join(HERE, '../dist/css/tiptap-email.css');

const HEADER = `/* ==========================================================================
   TipTap email
   GENERATED from tiptap-content.css by \`node bin/build-email-css.js\`: do not edit it, change the content stylesheet.
   Content classes with hard-coded values (no custom properties, no nesting) so that CSS inliners can process them.
   ========================================================================== */`;

/**
 * Splits `text` on `separator`, ignoring the ones inside parentheses.
 *
 * @param {string} text
 * @param {string} separator
 * @returns {string[]}
 */
const splitTopLevel = (text, separator) => {
  const parts = [];
  let depth = 0;
  let current = '';

  Array.from(text).forEach((char) => {
    if (char === '(') {
      depth += 1;
    } else if (char === ')') {
      depth -= 1;
    }

    if (char === separator && depth === 0) {
      parts.push(current);
      current = '';
    } else {
      current += char;
    }
  });
  parts.push(current);

  return parts.map((part) => part.trim()).filter(Boolean);
};

/**
 * Reads CSS into nodes: `{ comment }`, `{ declaration: [property, value] }` and `{ selector, children, inline }`
 * (`inline`: the rule was written on a single line).
 *
 * @param {string} css
 * @returns {Object[]}
 */
const parse = (css) => {
  let position = 0;

  const readBlock = (isRoot) => {
    const nodes = [];
    let buffer = '';
    let depth = 0;
    let start = position;

    const flushDeclaration = () => {
      const text = buffer.trim();

      if (text) {
        const colon = text.indexOf(':');

        nodes.push({ declaration: [text.slice(0, colon).trim(), text.slice(colon + 1).trim()] });
      }
      buffer = '';
    };

    while (position < css.length) {
      const char = css[position];

      if (char === '/' && css[position + 1] === '*') {
        const end = css.indexOf('*/', position);

        nodes.push({ comment: css.slice(position + 2, end).trim() });
        position = end + 2;
        start = position;
      } else if (char === '{') {
        const selector = buffer.trim();

        position += 1;
        const bodyStart = position;
        const children = readBlock(false);

        nodes.push({ selector, children, inline: !css.slice(bodyStart, position).includes('\n') });
        buffer = '';
        start = position;
      } else if (char === '}') {
        flushDeclaration();
        position += 1;

        return nodes;
      } else if (char === ';' && depth === 0) {
        flushDeclaration();
        position += 1;
      } else {
        if (char === '(') {
          depth += 1;
        } else if (char === ')') {
          depth -= 1;
        }
        buffer += char;
        position += 1;
      }
    }

    if (!isRoot) {
      throw new Error(`Unclosed block starting at ${start}`);
    }

    return nodes;
  };

  return readBlock(true);
};

/**
 * @param {Object[]} nodes
 * @returns {Map<string, string>} The custom properties of the `:root` rules
 */
const rootProperties = (nodes) => new Map(nodes
  .filter(({ selector }) => selector === ':root')
  .flatMap(({ children }) => children)
  .filter(({ declaration }) => declaration?.[0].startsWith('--'))
  .map(({ declaration: [name, value] }) => [name, value]));

const resolveVariables = (value, properties) => {
  let resolved = value;

  while (resolved.includes('var(')) {
    const start = resolved.indexOf('var(');
    let depth = 0;
    let end = start;

    for (; end < resolved.length; end += 1) {
      depth += { '(': 1, ')': -1 }[resolved[end]] ?? 0;

      if (depth === 0 && end > start + 3) {
        break;
      }
    }

    const [name, ...fallback] = splitTopLevel(resolved.slice(start + 4, end), ',');
    const replacement = properties.get(name) ?? (fallback.length ? fallback.join(',') : null);

    if (replacement === null) {
      throw new Error(`The custom property ${name} has no value and no fallback`);
    }

    resolved = `${resolved.slice(0, start)}${replacement}${resolved.slice(end + 1)}`;
  }

  return resolved;
};

const toHex = (channels) => `#${channels.map((channel) => Math.round(channel).toString(16).padStart(2, '0')).join('')}`;

const RGB = /rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)/g;

const convertValue = (value) => value
  .replace(/(-?\d*\.?\d+)rem\b/g, (_, rem) => `${Number((parseFloat(rem) * 16).toFixed(2))}px`)
  .replace(RGB, (_, r, g, b, alpha) => {
    const opacity = alpha === undefined ? 1 : parseFloat(alpha) / (alpha.endsWith('%') ? 100 : 1);

    // Blended on white: an email client does not know the alpha channel of this syntax
    return toHex([r, g, b].map((channel) => 255 - (255 - Number(channel)) * opacity));
  });

const sides = (property, values) => {
  const [first, second = first] = values.split(/\s+/);

  return {
    'margin-inline': [['margin-left', first], ['margin-right', second]],
    'margin-block': [['margin-top', first], ['margin-bottom', second]],
  }[property];
};

/**
 * @param {string} property
 * @param {string} value
 * @returns {string[][]} The declarations written in an email for a declaration of the content stylesheet
 */
const physical = (property, rawValue) => {
  const important = /\s*!important$/.test(rawValue) ? ' !important' : '';
  const value = rawValue.replace(/\s*!important$/, '');
  const declarations = {
    'margin-inline-start': [['margin-left', value]],
    'margin-inline-end': [['margin-right', value]],
    'margin-inline': sides('margin-inline', value),
    'margin-block': sides('margin-block', value),
  }[property] ?? [[property, value]];

  if (/-(inline|block)\b/.test(property) && !declarations.length) {
    throw new Error(`The logical property ${property} has no physical equivalent in the generator`);
  }

  return declarations.map(([name, text]) => [name, `${text}${important}`]);
};

/**
 * @param {Object} rule
 * @param {string[]} parents
 * @returns {{ selectors: string[], declarations: string[][], inline: boolean, comment?: string }[]}
 */
const flatten = ({ selector, children, inline }, parents = []) => {
  const own = splitTopLevel(selector, ',');
  const selectors = parents.length
    ? parents.flatMap((parent) => own.map((child) => (child.includes('&')
      ? child.replaceAll('&', parent)
      : `${parent} ${child}`)))
    : own;
  const declarations = children.filter((child) => child.declaration).map(({ declaration }) => declaration);
  const nested = children.filter((child) => child.selector !== undefined)
    .flatMap((child) => flatten(child, selectors));

  return [...(declarations.length ? [{ selectors, declarations, inline }] : []), ...nested];
};

const format = ({ selectors, declarations, inline }) => {
  if (inline && selectors.length === 1) {
    return `${selectors[0]} { ${declarations.map(([name, value]) => `${name}: ${value};`).join(' ')} }`;
  }

  return `${selectors.join(',\n')} {\n${declarations.map(([name, value]) => `  ${name}: ${value};`).join('\n')}\n}`;
};

/**
 * @param {string} contentCss The content stylesheet
 * @returns {string} The email stylesheet
 */
export const buildEmailCss = (contentCss) => {
  const nodes = parse(contentCss);
  const properties = rootProperties(nodes);
  const blocks = [];
  let comment = null;

  nodes.forEach((node, index) => {
    if (node.comment !== undefined) {
      // The first comment is the header of the file
      comment = index === 0 ? null : node.comment;

      return;
    }

    const rules = flatten(node)
      .filter(({ selectors }) => selectors.every((selector) => selector.startsWith('.ut-')))
      .map((rule) => ({
        ...rule,
        declarations: rule.declarations.flatMap(([name, value]) => physical(name, convertValue(
          resolveVariables(value, properties),
        ))),
      }));

    if (rules.length && comment) {
      blocks.push({ comment });
    }

    if (rules.length) {
      comment = null;
    }

    rules.forEach((rule) => blocks.push({ rule }));
  });

  const lines = [HEADER];
  let previous = null;

  blocks.forEach((block) => {
    const isMultiline = Boolean(block.rule) && !(block.rule.inline && block.rule.selectors.length === 1);

    // A blank line before a comment and after a rule which spans several lines
    if (block.comment || previous?.multiline || previous === null) {
      lines.push('');
    }

    lines.push(block.comment ? `/* ${block.comment} */` : format(block.rule));
    previous = { multiline: isMultiline };
  });

  const body = lines.join('\n');

  return `${body}\n`;
};

const main = () => {
  const generated = buildEmailCss(readFileSync(CONTENT_FILE, 'utf8'));

  if (process.argv.includes('--check')) {
    if (readFileSync(EMAIL_FILE, 'utf8') !== generated) {
      process.stderr.write('tiptap-email.css is out of date: run `node bin/build-email-css.js`.\n');
      process.exit(1);
    }

    return;
  }

  writeFileSync(EMAIL_FILE, generated);
};

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main();
}
