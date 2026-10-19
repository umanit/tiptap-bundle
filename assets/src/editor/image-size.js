/**
 * Size of an image as the `width` / `height` attribute the editor keeps, from a CSS length (`783px`) or a bare number.
 * Other units (`50%`, `10em`) have no equivalent in the attribute: `null`.
 *
 * @param {string|null|undefined} value
 * @returns {string|null}
 */
export default (value) => {
  const match = /^\s*(\d+(?:\.\d+)?)(?:px)?\s*$/.exec(value ?? '');

  return match ? String(Math.round(Number(match[1]))) : null;
};
