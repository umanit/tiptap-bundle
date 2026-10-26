/**
 * What is repaired on the HTML the editor is given, as Word and Google Docs write it.
 *
 * Its PHP twin is `Umanit\TiptapBundle\Cleaning` (`umanit:tiptap:clean`), which cleans the contents of a database. They
 * do not do the same work, and must not drift where they overlap:
 * - the classes `Mso*` and `WordSection*` are dropped by both (`WordClassesRule`); `tests/Fixtures/Cleaning/
 *   word-classes-cases.json` is replayed on each side. The editor drops more (`FORBIDDEN_CLASSES`) because it reads the
 *   classes of every content it loads: what the PHP side leaves in a database is dropped by the editor anyway;
 * - the alignment which Word writes as a huge margin, and the one a `<style>` block gives to a class, only exist at
 *   the time of a paste: a stored content already has what its author made of them.
 */

// Past this left margin (in px), a block is taken for a right-aligned one rather than for an indented one.
export const RIGHT_ALIGN_MARGIN = 250;

const SHORTHAND_LEFT_MARGIN = /margin\s*:\s*[0-9.a-z]+\s+[0-9.a-z]+\s+[0-9.a-z]+\s+([0-9.]+)px/i;
const LEFT_MARGIN = /margin-left\s*:\s*([0-9.]+)px/i;
const STYLE_BLOCK = /<style[^>]*>([\s\S]*?)<\/style>/i;
const CLASS_ALIGNMENT = /\.([a-zA-Z0-9_-]+)[^}]*text-align\s*:\s*(left|right|center|justify)/gi;
// What `normalizeWordHtml` looks at: an inline margin, or a style block
const NEEDS_NORMALIZING = /margin|<style/i;

/**
 * The left margin an inline style gives, in px: `margin-left`, or the fourth value of a four-values `margin` (the
 * other shorthands give the same margin to both sides, which is not an alignment).
 *
 * @param {string} style
 * @returns {number} 0 when the style has none
 */
export const leftMarginOf = (style) => {
  const longhand = style.match(LEFT_MARGIN);
  if (longhand) {
    return parseFloat(longhand[1]);
  }

  const shorthand = style.match(SHORTHAND_LEFT_MARGIN);

  return shorthand ? parseFloat(shorthand[1]) : 0;
};

/**
 * A block with a huge left margin was right-aligned: it becomes so, without the margin.
 *
 * @param {Document} doc
 */
export const alignHugeMargins = (doc) => {
  doc.querySelectorAll('p, div, span').forEach((el) => {
    const style = el.getAttribute('style');

    if (style && leftMarginOf(style) > RIGHT_ALIGN_MARGIN) {
      el.style.textAlign = 'right';
      el.style.margin = '0px';
    }
  });
};

/**
 * The alignment a `<style>` block gives to a class goes inline, unless the element already has one.
 *
 * @param {Document} doc
 * @param {string} html
 */
export const inlineClassAlignments = (doc, html) => {
  const css = html.match(STYLE_BLOCK)?.[1];

  if (!css) {
    return;
  }

  Array.from(css.matchAll(CLASS_ALIGNMENT)).forEach(([, className, alignment]) => {
    try {
      doc.querySelectorAll(`.${className}`).forEach((el) => {
        if (!el.style.textAlign) {
          el.style.textAlign = alignment;
        }
      });
    } catch (e) {
      console.warn(`HtmlCleaner: Cannot apply the style of the class ".${className}"`, e);
    }
  });
};

const HtmlCleaner = {
  FORBIDDEN_CLASSES: [
    /^Mso/, // Word Desktop (twin: WordClassesRule)
    /^WordSection/, // Word Desktop, the wrapper of the document (twin: WordClassesRule)
    /^SCX/, // Word Online
    /^kix-/, // Google Docs
    /^[a-z0-9]{20,}$/, // Hashs/Noise
  ],

  normalizeWordHtml(html) {
    if (!html || !NEEDS_NORMALIZING.test(html)) {
      return html;
    }

    try {
      const doc = new DOMParser().parseFromString(html, 'text/html');

      alignHugeMargins(doc);
      inlineClassAlignments(doc, html);

      return doc.body.innerHTML;
    } catch {
      return html;
    }
  },

  parseClass(element) {
    const classString = element.getAttribute('class');
    if (!classString) {
      return null;
    }

    const classes = classString.split(/\s+/);
    const validClasses = classes.filter(
      (cls) => !this.FORBIDDEN_CLASSES.some((pattern) => pattern.test(cls)),
    );

    return validClasses.length > 0 ? validClasses.join(' ') : null;
  },
};

export default HtmlCleaner;
