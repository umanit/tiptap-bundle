/**
 * Indentation levels: `margin-left` in multiples of 40px (what CKEditor writes) as the `ut-indent-N` classes.
 * Any other value has no class: it is not converted.
 */
export const INDENT_STEP = 40;
export const INDENT_MAX_LEVEL = 8;

/**
 * @param {string|null|undefined} value A `margin-left`
 * @returns {string|null}
 */
export const indentClass = (value) => {
  const match = /^\s*(\d+(?:\.\d+)?)px\s*$/.exec(value ?? '');
  const level = match ? Number(match[1]) / INDENT_STEP : 0;

  return Number.isInteger(level) && level >= 1 && level <= INDENT_MAX_LEVEL ? `ut-indent-${level}` : null;
};

/**
 * @param {CSSStyleDeclaration} style
 * @returns {string|null}
 */
export const indentClassOf = (style) => indentClass(style.getPropertyValue('margin-left'));
