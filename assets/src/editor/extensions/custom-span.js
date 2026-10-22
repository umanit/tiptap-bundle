import { Mark } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';
import { INLINE_STYLE_TAGS, resolveLegacyClass } from '../legacy-styles.js';

const NAME = 'textSpan';

// The classes the toolbar manages, by the inline style another editor writes for them.
const COLOR = { prefix: 'ut-color-', property: 'color' };
const BACKGROUND = { prefix: 'ut-bg-', property: 'backgroundColor' };
const SIZE = { prefix: 'ut-fs-', property: 'fontSize' };

const classesOf = (value) => (value ?? '').split(' ').filter(Boolean);

/**
 * @param {HTMLElement} element
 * @param {Object|null} legacyStyles
 * @returns {string[]} The classes of the element, plus the ones its inline style maps to (legacy import mode) when the
 *   element does not have its own class for the property
 */
const readClasses = (element, legacyStyles) => {
  const classes = classesOf(HtmlCleaner.parseClass(element));

  [COLOR, BACKGROUND, SIZE].forEach(({ prefix, property }) => {
    const own = classes.some((cls) => cls.startsWith(prefix));
    const legacy = own ? null : resolveLegacyClass(legacyStyles, property, element);

    if (legacy) {
      classes.push(legacy);
    }
  });

  return classes;
};

/**
 * The span marks of a text, restyled: `className` replaces the class of the family (`ut-color-*`…) where the text
 * already has one, else it goes in the first span; null removes the family. The spans keep their order, so the nesting
 * of the source is not touched.
 *
 * @param {import('@tiptap/pm/model').Mark[]} spans
 * @param {import('@tiptap/pm/model').MarkType} type
 * @param {string} prefix
 * @param {string|null} className
 * @returns {import('@tiptap/pm/model').Mark[]}
 */
const restyle = (spans, type, prefix, className) => {
  const strip = (mark) => classesOf(mark.attrs.class).filter((cls) => !cls.startsWith(prefix));
  const owner = spans.findIndex((mark) => classesOf(mark.attrs.class).some((cls) => cls.startsWith(prefix)));
  const target = -1 === owner ? 0 : owner;

  if (className && 0 === spans.length) {
    return [type.create({ class: className })];
  }

  return spans
    .map((mark, index) => {
      const classes = strip(mark);

      if (className && index === target) {
        // At the place of the class it replaces, to keep the order of the classes
        const at = classesOf(mark.attrs.class).findIndex((cls) => cls.startsWith(prefix));

        classes.splice(-1 === at ? classes.length : at, 0, className);
      }

      return classes.length > 0 ? type.create({ class: classes.join(' ') }) : null;
    })
    .filter(Boolean);
};

const sameMarks = (a, b) => a.length === b.length && a.every((mark, index) => mark.eq(b[index]));

const restyleCommand = (prefix, className) => ({ state, tr, dispatch }) => {
  const type = state.schema.marks[NAME];
  const { selection } = state;

  if (!dispatch) {
    return true;
  }

  if (selection.empty) {
    const marks = state.storedMarks ?? selection.$from.marks();
    const others = marks.filter((mark) => mark.type !== type);
    const next = restyle(marks.filter((mark) => mark.type === type), type, prefix, className);

    tr.setStoredMarks(next.reduce((set, mark) => mark.addToSet(set), others));

    return true;
  }

  selection.ranges.forEach(({ $from, $to }) => {
    const from = $from.pos;
    const to = $to.pos;

    tr.doc.nodesBetween(from, to, (node, pos) => {
      if (!node.isText) {
        return;
      }

      const spans = node.marks.filter((mark) => mark.type === type);
      const next = restyle(spans, type, prefix, className);

      if (sameMarks(spans, next)) {
        return;
      }

      const start = Math.max(pos, from);
      const end = Math.min(pos + node.nodeSize, to);

      tr.removeMark(start, end, type);
      next.forEach((mark) => tr.addMark(start, end, mark));
    });
  });

  return true;
};

/**
 * @param {import('@tiptap/pm/state').EditorState} state
 * @param {string} className
 * @returns {boolean} Whether the whole selection (or the cursor) is in a span which has the class
 */
export const hasSpanClass = (state, className) => {
  const type = state.schema.marks[NAME];
  const { selection } = state;
  const has = (marks) => marks.some((mark) => mark.type === type && classesOf(mark.attrs.class).includes(className));

  if (selection.empty) {
    return has(state.storedMarks ?? selection.$from.marks());
  }

  let active = true;

  state.doc.nodesBetween(selection.from, selection.to, (node) => {
    if (node.isText && !has(node.marks)) {
      active = false;
    }
  });

  return active;
};

/**
 * Every `<span>` of the source is one mark with its own classes, and comes out as one `<span>`: the editor does not
 * merge nor split them. The classes are the ones of the palette (`ut-color-*`, `ut-bg-*`, `ut-fs-*`, set from the
 * toolbar) and the ones of the site (`text-primary`…), which the editor only keeps. The same goes for the classes of
 * a formatting element (`<strong class="text-primary">`): they go on a span around the text.
 */
export default Mark.create({
  name: NAME,

  // Above the other marks (100): the span comes out around `<strong>`, `<em>`…, not inside.
  priority: 200,

  // A span can be nested in another one: they are two marks.
  excludes: '',

  addOptions() {
    return { legacyStyles: null };
  },

  addAttributes() {
    return {
      class: {
        default: null,
        parseHTML: (element) => {
          const classes = readClasses(element, this.options.legacyStyles);

          return classes.length > 0 ? classes.join(' ') : null;
        },
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },

  parseHTML() {
    return [{
      // CKEditor may put the style on a formatting element (`<strong style="font-size:10pt">`) rather than on a span.
      tag: INLINE_STYLE_TAGS,
      // Read before the rule of the formatting element (bold…) which consumes it, or this one is never reached.
      priority: 60,
      consuming: false,
      getAttrs: (element) => (readClasses(element, this.options.legacyStyles).length > 0 ? null : false),
    }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['span', HTMLAttributes, 0];
  },

  addCommands() {
    return {
      setTextColor: (className) => restyleCommand(COLOR.prefix, className || null),
      unsetTextColor: () => restyleCommand(COLOR.prefix, null),
      setTextBgColor: (className) => restyleCommand(BACKGROUND.prefix, className || null),
      unsetTextBgColor: () => restyleCommand(BACKGROUND.prefix, null),
      setTextSize: (className) => restyleCommand(SIZE.prefix, className || null),
      unsetTextSize: () => restyleCommand(SIZE.prefix, null),
    };
  },
});
