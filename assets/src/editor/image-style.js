/**
 * What the inline style of an image (`float`, `margin`, centering) means in utility classes, the way the image form
 * of the editor writes them. A style which has no class is not converted.
 */
const MARGIN_CLASSES = { '5px': 'ut-ma-5', '10px': 'ut-ma-10' };
const SIDES = ['top', 'right', 'bottom', 'left'];

/**
 * @param {CSSStyleDeclaration} style
 * @returns {{ classes: string[], properties: string[] }} Classes, and the style properties they replace
 */
export default (style) => {
  const get = (property) => style.getPropertyValue(property).trim();
  const classes = [];
  const properties = [];

  const float = get('float');

  if (float === 'left' || float === 'right') {
    classes.push(float === 'left' ? 'ut-float-start' : 'ut-float-end');
    properties.push('float');
  }

  const margins = SIDES.map((side) => get(`margin-${side}`));

  if (margins[0] !== '' && margins.every((margin) => margin === margins[0]) && MARGIN_CLASSES[margins[0]]) {
    classes.push(MARGIN_CLASSES[margins[0]]);
    properties.push('margin', ...SIDES.map((side) => `margin-${side}`));
  } else if (get('display') === 'block' && get('margin-left') === 'auto' && get('margin-right') === 'auto') {
    classes.push('ut-block', 'ut-mx-auto');
    properties.push('display', 'margin-left', 'margin-right');
  }

  return { classes, properties };
};
