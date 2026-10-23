/**
 * Formatting "signals" of an HTML fragment: tags, attributes, inline style properties and classes.
 *
 * Comparing the signals of a content before and after a round trip through the editor tells what the schema
 * dropped. The signals are normalized so that what the editor merely rewrites is not reported as a loss.
 */
import imageSize from '../editor/image-size.js';
import imageStyle from '../editor/image-style.js';
import { BLOCK_TAGS, marginClassesOf } from '../editor/indent.js';
import { isWidthConverted } from '../editor/column-widths.js';

// Tags the editor renders with another one (`<b>` becomes `<strong>`).
const TAG_ALIASES = {
  b: 'strong', i: 'em', strike: 's', del: 's',
};

// Inline marks: measured in covered characters, because the editor merges adjacent ones (`<b>a</b><b>b</b>`).
const MARK_TAGS = new Set(['strong', 'em', 'u', 's', 'sub', 'sup']);

// Tags which only exist to carry a style or an attribute: their loss is reported through those.
const CARRIER_TAGS = new Set(['span']);

// A utility class is the editor's equivalent of an inline style: the style is not lost when the class is there.
const CLASS_EQUIVALENCES = [
  [/^ut-text-(left|center|right|justify)$/, 'style:text-align'],
  [/^ut-color-/, 'style:color'],
  [/^ut-bg-/, 'style:background-color'],
  [/^ut-fs-/, 'style:font-size'],
];

// Properties measured in covered characters too, because the editor merges adjacent identical spans.
const COVERAGE_STYLES = new Set(['color', 'background-color', 'font-size']);

// Style properties reported as a single signal (`border-top-width`, `border-left-color`... become `border*`).
const STYLE_FAMILIES = [
  [/^border/, 'border*'],
  [/^padding/, 'padding*'],
  [/^mso-/, 'mso-*'],
  [/^text-decoration/, 'text-decoration'],
];

const styleSignal = (property) => `style:${STYLE_FAMILIES.find(([pattern]) => pattern.test(property))?.[1] ?? property}`;

const BOLD_WEIGHT = /^(bold(er)?|[5-9]\d{2,})$/;

// Marks an inline style stands for (`font-weight:bold` is `<strong>`): the editor reads them as such.
const styleMarks = (style) => {
  const marks = new Set();
  const decoration = `${style.getPropertyValue('text-decoration')} ${style.getPropertyValue('text-decoration-line')}`;

  if (BOLD_WEIGHT.test(style.getPropertyValue('font-weight').trim())) {
    marks.add('strong');
  }

  if (style.getPropertyValue('font-style').trim() === 'italic') {
    marks.add('em');
  }

  if (decoration.includes('underline')) {
    marks.add('u');
  }

  if (decoration.includes('line-through')) {
    marks.add('s');
  }

  return marks;
};

// Style properties the editor turns into something it keeps: a mark, a utility class.
const convertedProperties = (el, tag) => {
  const converted = new Set();
  const marks = styleMarks(el.style);

  if (marks.has('strong')) {
    converted.add('font-weight');
  }

  if (marks.has('em')) {
    converted.add('font-style');
  }

  if (marks.has('u') || marks.has('s')) {
    Array.from(el.style).filter((property) => property.startsWith('text-decoration')).forEach((p) => converted.add(p));
  }

  if (tag === 'img') {
    imageStyle(el.style).properties.forEach((property) => converted.add(property));
  }

  if (BLOCK_TAGS.test(tag)) {
    marginClassesOf(el.style).properties.forEach((property) => converted.add(property));
  }

  return converted;
};

// Properties whose initial value is what the browser applies without the declaration, on any element.
const INITIAL_VALUES = [
  [/^font-weight$/, ['normal', '400']],
  [/^font-style$/, ['normal']],
  [/^text-decoration/, ['none']],
  [/^white-space$/, ['normal']],
];

// Attributes whose HTML default value is the one written.
const DEFAULT_ATTRIBUTES = {
  'ol@start': '1', 'td@colspan': '1', 'td@rowspan': '1', 'th@colspan': '1', 'th@rowspan': '1',
};

const LIST_TAGS = new Set(['ul', 'ol']);

// Number of `ul`/`ol` ancestors of an element: the nesting level of a list.
const listLevel = (el) => {
  let level = 0;

  for (let parent = el.parentElement; parent; parent = parent.parentElement) {
    if (LIST_TAGS.has(parent.tagName.toLowerCase())) {
      level += 1;
    }
  }

  return level;
};

// Marker the browser draws by default for a list at that level.
const defaultListStyleType = (rawTag, level) => {
  if (rawTag === 'ol') {
    return 'decimal';
  }

  return ['disc', 'circle'][level] ?? 'square';
};

// The `left` alignment is the default one unless the writing direction is right-to-left.
const isLeftToRight = (el) => (el.closest('[dir]')?.getAttribute('dir') ?? 'ltr').trim().toLowerCase() === 'ltr';

// A declaration which sets what the browser applies anyway: dropping it changes nothing on screen.
const isDefaultStyle = (el, rawTag, property) => {
  const value = el.style.getPropertyValue(property).trim().toLowerCase();

  if (property === 'text-align') {
    return value === 'start' || (value === 'left' && isLeftToRight(el));
  }

  if (property === 'list-style-type') {
    return LIST_TAGS.has(rawTag) && value === defaultListStyleType(rawTag, listLevel(el));
  }

  return INITIAL_VALUES.some(([pattern, values]) => pattern.test(property) && values.includes(value));
};

const isDefaultAttribute = (el, rawTag, name) => DEFAULT_ATTRIBUTES[`${rawTag}@${name}`] === el.getAttribute(name).trim();

// Ids of the elements which are not anchors: where the editor moves the identifier of an `<a id>`.
const idsOfContent = (body) => new Set(
  Array.from(body.querySelectorAll('[id]')).filter((el) => el.tagName !== 'A').map((el) => el.id),
);

// A named anchor whose identifiers are found on an element of the produced content: the `#x` link still works.
const isReplacedAnchor = (el, rawTag, producedIds) => rawTag === 'a'
  && !el.hasAttribute('href')
  && (el.hasAttribute('id') || el.hasAttribute('name'))
  && ['id', 'name'].filter((name) => el.hasAttribute(name)).every((name) => producedIds.has(el.getAttribute(name)));

const increment = (signals, key, by = 1) => signals.set(key, (signals.get(key) ?? 0) + by);

// Coverage signals (`style:color`...) an element carries, through an inline style or an equivalent utility class.
const coverageSignalsOf = (el) => {
  const found = new Set();

  Array.from(el.style).filter((property) => COVERAGE_STYLES.has(property)).forEach((p) => found.add(`style:${p}`));
  el.classList.forEach((cls) => {
    CLASS_EQUIVALENCES.filter(([pattern, style]) => pattern.test(cls) && COVERAGE_STYLES.has(style.slice(6)))
      .forEach(([, style]) => found.add(style));
  });

  return found;
};

const countMarkedCharacters = (root, signals) => {
  const walker = root.ownerDocument.createTreeWalker(root, 4 /* NodeFilter.SHOW_TEXT */);

  while (walker.nextNode()) {
    const { length } = walker.currentNode.textContent.replace(/\s+/g, '');

    if (length > 0) {
      const marks = new Set();
      const coverage = new Set();

      for (let el = walker.currentNode.parentElement; el && el !== root; el = el.parentElement) {
        const tag = TAG_ALIASES[el.tagName.toLowerCase()] ?? el.tagName.toLowerCase();

        if (MARK_TAGS.has(tag)) {
          marks.add(tag);
        }

        styleMarks(el.style).forEach((mark) => marks.add(mark));
        coverageSignalsOf(el).forEach((signal) => coverage.add(signal));
      }

      marks.forEach((tag) => increment(signals, `mark:${tag}`, length));
      coverage.forEach((signal) => increment(signals, signal, length));
    }
  }
};

/**
 * @param {string} html
 * @param {DOMParser} parser
 * @returns {{ signals: Map<string, number>, text: string, ids: Set<string> }}
 */
// The `align` attribute of a table (center, right) and of a cell is kept by the editor, as a class.
const isConvertedAlign = (el, rawTag, name) => {
  if (name !== 'align') {
    return false;
  }

  const align = el.getAttribute('align').toLowerCase();

  if (rawTag === 'table') {
    return ['center', 'right'].includes(align);
  }

  return ['td', 'th'].includes(rawTag) && ['left', 'center', 'right', 'justify'].includes(align);
};

// In legacy import mode, the width of a cell becomes the width of its column.
export const collectSignals = (html, parser, { convertCellWidths = false, producedIds = null } = {}) => {
  const { body } = parser.parseFromString(html, 'text/html');
  const signals = new Map();
  const identifiers = producedIds ?? idsOfContent(body);

  body.querySelectorAll('*').forEach((el) => {
    const rawTag = el.tagName.toLowerCase();
    const tag = TAG_ALIASES[rawTag] ?? rawTag;

    const replacedAnchor = isReplacedAnchor(el, rawTag, identifiers);
    // A `<div>` without attribute only wraps its content, which is kept (and measured) on its own.
    const isBareDiv = rawTag === 'div' && el.attributes.length === 0;

    if (!MARK_TAGS.has(tag) && !CARRIER_TAGS.has(tag) && !replacedAnchor && !isBareDiv) {
      increment(signals, `tag:${tag}`);
    }

    // The size of an image: the editor keeps it as attributes, whether it was set as an attribute or inline.
    const imageSizes = new Set();
    const converted = convertedProperties(el, rawTag);
    const isConvertedCellWidth = convertCellWidths && ['td', 'th'].includes(rawTag) && isWidthConverted(el);

    if (isConvertedCellWidth) {
      converted.add('width');
    }
    const isImageSize = (property) => rawTag === 'img' && ['width', 'height'].includes(property);

    Array.from(el.attributes).forEach(({ name }) => {
      if (name === 'style') {
        new Set(Array.from(el.style)
          .filter((property) => !converted.has(property) && !isDefaultStyle(el, rawTag, property)).map((property) => {
            if (COVERAGE_STYLES.has(property)) {
              return null;
            }

            if (isImageSize(property)) {
              if (imageSize(el.style.getPropertyValue(property)) !== null) {
                imageSizes.add(`attr:img@${property}`);

                return null;
              }

              return `style:img@${property}`;
            }

            return styleSignal(property);
          })).forEach((signal) => signal && increment(signals, signal));
      } else if (name === 'class') {
        el.classList.forEach((cls) => {
          increment(signals, `class:${cls}`);
          CLASS_EQUIVALENCES
            .filter(([pattern, style]) => pattern.test(cls) && !COVERAGE_STYLES.has(style.slice(6)))
            .forEach(([, style]) => increment(signals, style));
        });
      } else if (isImageSize(name)) {
        imageSizes.add(`attr:img@${name}`);
      } else if (
        !isConvertedAlign(el, rawTag, name)
        && !(isConvertedCellWidth && name === 'width')
        && !isDefaultAttribute(el, rawTag, name)
        && !(replacedAnchor && ['id', 'name'].includes(name))
      ) {
        increment(signals, `attr:${rawTag}@${name}`);
      }
    });

    imageSizes.forEach((signal) => increment(signals, signal));
  });

  countMarkedCharacters(body, signals);

  return { signals, text: body.textContent.replace(/\s+/g, ''), ids: idsOfContent(body) };
};

/**
 * Signals which are less present in `after` than in `before`.
 *
 * @param {Map<string, number>} before
 * @param {Map<string, number>} after
 * @returns {{ signal: string, before: number, after: number }[]}
 */
export const lostSignals = (before, after) => Array.from(before)
  .filter(([signal, count]) => (after.get(signal) ?? 0) < count)
  .map(([signal, count]) => ({ signal, before: count, after: after.get(signal) ?? 0 }));
