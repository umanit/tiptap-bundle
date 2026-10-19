/**
 * Formatting "signals" of an HTML fragment: tags, attributes, inline style properties and classes.
 *
 * Comparing the signals of a content before and after a round trip through the editor tells what the schema
 * dropped. The signals are normalized so that what the editor merely rewrites is not reported as a loss.
 */
import imageSize from '../editor/image-size.js';

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

// Style properties reported as a single signal (`border-top-width`, `border-left-color`... become `border*`).
const STYLE_FAMILIES = [
  [/^border/, 'border*'],
  [/^padding/, 'padding*'],
  [/^mso-/, 'mso-*'],
  [/^text-decoration/, 'text-decoration'],
];

const styleSignal = (property) => `style:${STYLE_FAMILIES.find(([pattern]) => pattern.test(property))?.[1] ?? property}`;

const increment = (signals, key, by = 1) => signals.set(key, (signals.get(key) ?? 0) + by);

const countMarkedCharacters = (root, signals) => {
  const walker = root.ownerDocument.createTreeWalker(root, 4 /* NodeFilter.SHOW_TEXT */);

  while (walker.nextNode()) {
    const { length } = walker.currentNode.textContent.replace(/\s+/g, '');

    if (length > 0) {
      const marks = new Set();

      for (let el = walker.currentNode.parentElement; el && el !== root; el = el.parentElement) {
        const tag = TAG_ALIASES[el.tagName.toLowerCase()] ?? el.tagName.toLowerCase();

        if (MARK_TAGS.has(tag)) {
          marks.add(tag);
        }
      }

      marks.forEach((tag) => increment(signals, `mark:${tag}`, length));
    }
  }
};

/**
 * @param {string} html
 * @param {DOMParser} parser
 * @returns {{ signals: Map<string, number>, text: string }}
 */
export const collectSignals = (html, parser) => {
  const { body } = parser.parseFromString(html, 'text/html');
  const signals = new Map();

  body.querySelectorAll('*').forEach((el) => {
    const rawTag = el.tagName.toLowerCase();
    const tag = TAG_ALIASES[rawTag] ?? rawTag;

    if (!MARK_TAGS.has(tag) && !CARRIER_TAGS.has(tag)) {
      increment(signals, `tag:${tag}`);
    }

    // The size of an image: the editor keeps it as attributes, whether it was set as an attribute or inline.
    const imageSizes = new Set();
    const isImageSize = (property) => rawTag === 'img' && ['width', 'height'].includes(property);

    Array.from(el.attributes).forEach(({ name }) => {
      if (name === 'style') {
        new Set(Array.from(el.style).map((property) => {
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
          CLASS_EQUIVALENCES.filter(([pattern]) => pattern.test(cls)).forEach(([, style]) => increment(signals, style));
        });
      } else if (isImageSize(name)) {
        imageSizes.add(`attr:img@${name}`);
      } else {
        increment(signals, `attr:${rawTag}@${name}`);
      }
    });

    imageSizes.forEach((signal) => increment(signals, signal));
  });

  countMarkedCharacters(body, signals);

  return { signals, text: body.textContent.replace(/\s+/g, '') };
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
