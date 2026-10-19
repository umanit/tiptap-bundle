/**
 * Horizontal margins of a block as utility classes: `margin-left` in multiples of 40px (what CKEditor writes) as
 * `ut-indent-N`, `0` as `ut-indent-0` and a `margin-right` of `0` as `ut-me-0`. A zero is not a no-op: inline, it
 * overrides the margin the stylesheet of the site gives to lists. Any other value has no class: it is not converted.
 */
export const INDENT_STEP = 40;
export const INDENT_MAX_LEVEL = 8;

// Blocks of the schema which carry a class.
export const BLOCK_TAGS = /^(p|h[1-6]|ul|ol)$/;

/**
 * @param {string|null|undefined} value A `margin-left`
 * @returns {string|null}
 */
export const indentClass = (value) => {
  const match = /^\s*(\d+(?:\.\d+)?)(?:px)?\s*$/.exec(value ?? '');
  const level = match ? Number(match[1]) / INDENT_STEP : -1;

  return Number.isInteger(level) && level >= 0 && level <= INDENT_MAX_LEVEL ? `ut-indent-${level}` : null;
};

/**
 * @param {CSSStyleDeclaration} style
 * @returns {{ classes: string[], properties: string[] }} Classes, and the style properties they replace
 */
export const marginClassesOf = (style) => {
  const classes = [];
  const properties = [];
  const indent = indentClass(style.getPropertyValue('margin-left'));

  if (indent !== null) {
    classes.push(indent);
    properties.push('margin-left');
  }

  if (/^0(px)?$/.test(style.getPropertyValue('margin-right').trim())) {
    classes.push('ut-me-0');
    properties.push('margin-right');
  }

  return { classes, properties };
};
