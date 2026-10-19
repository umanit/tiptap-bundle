import { normalizeColor, normalizeFontSize } from '../editor/legacy-styles.js';

/**
 * Distinct normalized values of the inline colors, background colors and font sizes of an HTML fragment.
 *
 * It feeds the inventory the palette is built from: the values are normalized the way the legacy import mode
 * normalizes them, so that what is counted together is converted together.
 *
 * @param {string} html
 * @param {DOMParser} parser
 * @returns {{ color: string[], backgroundColor: string[], fontSize: string[] }}
 */
export default (html, parser) => {
  const found = { color: new Set(), backgroundColor: new Set(), fontSize: new Set() };
  const { body } = parser.parseFromString(html, 'text/html');

  body.querySelectorAll('[style]').forEach(({ style }) => {
    [
      ['color', normalizeColor],
      ['backgroundColor', normalizeColor],
      ['fontSize', normalizeFontSize],
    ].forEach(([property, normalize]) => {
      const value = normalize(style[property]);

      if (value) {
        found[property].add(value);
      }
    });
  });

  return {
    color: [...found.color].sort(),
    backgroundColor: [...found.backgroundColor].sort(),
    fontSize: [...found.fontSize].sort(),
  };
};
