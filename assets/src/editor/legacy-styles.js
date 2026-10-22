/**
 * Legacy import mode: maps the inline styles another editor (CKEditor…) wrote onto the palette classes of the
 * schema. It is opt-in: without a map, the schema reads classes only and an inline style is dropped.
 *
 * The map is `{ color: { '#ff0000': 'ut-color-red' }, backgroundColor: { … }, fontSize: { '14px': 'ut-fs-14' } }`.
 * Keys are normalized (see `normalizeColor` and `normalizeFontSize`), so `rgb(255, 0, 0)`, `#F00` and `#ff0000` are
 * the same color and `10.5pt` is `14px`.
 */

/** Selector of the inline elements which can carry a color, a background or a size, read by the marks of the schema. */
export const INLINE_STYLE_TAGS = 'span, strong, b, em, i, u, s, strike, del, sub, sup, mark';

export const PROPERTIES = ['color', 'backgroundColor', 'fontSize'];

const round = (value) => Math.round(value * 100) / 100;

/**
 * @param {string|null|undefined} value
 * @returns {string|null} `#rrggbb` for a hex or rgb() color, the lowercase value for a color name, null when empty
 */
export const normalizeColor = (value) => {
  const color = (value ?? '').trim().toLowerCase();

  if ('' === color) {
    return null;
  }

  const hex = color.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/);

  if (hex) {
    const digits = 3 === hex[1].length ? Array.from(hex[1], (digit) => digit + digit).join('') : hex[1];

    return `#${digits}`;
  }

  const rgb = color.match(/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})\s*(?:[,/]\s*([\d.]+%?)\s*)?\)$/);

  if (rgb && (undefined === rgb[4] || 1 === parseFloat(rgb[4]))) {
    return `#${rgb.slice(1, 4)
      .map((channel) => Math.min(255, Number(channel)).toString(16).padStart(2, '0'))
      .join('')}`;
  }

  return color;
};

/**
 * @param {string|null|undefined} value
 * @returns {string|null} `<n>px` for a px or pt size (1pt = 4/3px), the lowercase value for any other unit
 */
export const normalizeFontSize = (value) => {
  const size = (value ?? '').trim().toLowerCase();
  const match = size.match(/^(\d*\.?\d+)(px|pt)$/);

  if (!match) {
    return '' === size ? null : size;
  }

  return `${round('pt' === match[2] ? (parseFloat(match[1]) * 4) / 3 : parseFloat(match[1]))}px`;
};

const NORMALIZERS = {
  color: normalizeColor,
  backgroundColor: normalizeColor,
  fontSize: normalizeFontSize,
};

const resolvers = new WeakMap();

const buildResolver = (map) => Object.fromEntries(PROPERTIES.map((property) => [
  property,
  new Map(Object.entries(map[property] ?? {}).map(([key, cls]) => [NORMALIZERS[property](key), cls])),
]));

/**
 * @param {Object|null|undefined} map
 * @param {'color'|'backgroundColor'|'fontSize'} property
 * @param {HTMLElement} element
 * @returns {string|null} The palette class for the element's inline style, null when there is none or no match
 */
export const resolveLegacyClass = (map, property, element) => {
  if (!map) {
    return null;
  }

  if (!resolvers.has(map)) {
    resolvers.set(map, buildResolver(map));
  }

  const value = NORMALIZERS[property](element.style?.[property]);

  return value ? (resolvers.get(map)[property].get(value) ?? null) : null;
};
