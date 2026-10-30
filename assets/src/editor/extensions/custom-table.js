import { Table, TableCell, TableHeader } from '@tiptap/extension-table';
import { mergeAttributes } from '@tiptap/core';
import HtmlCleaner from '../html-cleaner.js';
import { resolveLegacyClass } from '../legacy-styles.js';
import { colgroupWidth, widthsFromCells } from '../column-widths.js';

// Column widths are written as <col width="Xpx"> (a presentational attribute, not an inline style, so CSP compliant).
// Only the first row is read: that is where the parent (and the browsers) look for the grid too.
function columnWidths(node) {
  const row = node.firstChild;
  const widths = [];

  for (let i = 0; row && i < row.childCount; i += 1) {
    const { colspan, colwidth } = row.child(i).attrs;

    for (let j = 0; j < colspan; j += 1) {
      widths.push(colwidth?.[j] || 0);
    }
  }

  return widths.some(Boolean) ? widths : [];
}

// The caption is a plain text, kept as an attribute of the table: <caption> must be the first child of <table>,
// which the single content hole of the table (<tbody>) could not hold as an editable node.
function renderCaption(node) {
  return node.attrs.caption ? [['caption', node.attrs.caption]] : [];
}

function renderColgroup(node) {
  const widths = columnWidths(node);

  return widths.length ? [['colgroup', ...widths.map((width) => (width ? ['col', { width }] : ['col']))]] : [];
}

// The parent's TableView copies the attributes once and its update() ignores them: the class of a table (its width
// or alignment) did not change on screen. Without any node view, ProseMirror reuses the <table> while its own
// attributes are unchanged, so a new column width (which lives on the cells) did not show either. This one follows
// both.
class TableView {
  constructor(node, HTMLAttributes) {
    this.node = node;
    this.dom = document.createElement('table');
    Object.entries(HTMLAttributes).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        this.dom.setAttribute(key, String(value));
      }
    });
    this.contentDOM = this.dom.appendChild(document.createElement('tbody'));
    this.#syncCaption();
    this.#syncColgroup();
  }

  update(node) {
    if (node.type !== this.node.type) {
      return false;
    }

    // Another class: let ProseMirror build the view again
    if (node.attrs.class !== this.node.attrs.class) {
      return false;
    }

    this.node = node;
    this.#syncCaption();
    this.#syncColgroup();

    return true;
  }

  ignoreMutation(mutation) {
    return !this.contentDOM.contains(mutation.target);
  }

  #syncCaption() {
    this.dom.querySelector(':scope > caption')?.remove();

    const [caption] = renderCaption(this.node);
    if (caption) {
      const element = document.createElement('caption');
      element.textContent = caption[1];
      this.dom.prepend(element);
    }
  }

  #syncColgroup() {
    this.dom.querySelector(':scope > colgroup')?.remove();

    const [colgroup] = renderColgroup(this.node);
    if (colgroup) {
      const [, ...cols] = colgroup;
      const element = document.createElement('colgroup');
      cols.forEach(([, attributes]) => {
        const col = element.appendChild(document.createElement('col'));
        if (attributes) {
          col.setAttribute('width', String(attributes.width));
        }
      });
      this.dom.insertBefore(element, this.contentDOM);
    }
  }
}

// The classes Word and the like leave (`MsoTableGrid`…) are dropped, as on the paragraphs.
// `<table align="center">` (what CKEditor writes) becomes the class the schema uses, unless the table has one already.
function tableClass(element) {
  const classes = (HtmlCleaner.parseClass(element) ?? '').split(' ').filter(Boolean);
  const align = (element.getAttribute('align') || '').toLowerCase();

  if (['center', 'right'].includes(align) && !classes.some((c) => /^ut-table-(center|right)$/.test(c))) {
    classes.push(`ut-table-${align}`);
  }

  return classes.join(' ') || null;
}

export const CustomTable = Table.extend({
  addNodeView() {
    return ({ node, HTMLAttributes }) => new TableView(
      node,
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
    );
  },

  // Override renderHTML to prevent the parent from generating:
  // - style="min-width: Xpx" on <table>
  // - <colgroup><col style="min-width: Xpx"></colgroup>
  renderHTML({ node, HTMLAttributes }) {
    return [
      'table',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
      ...renderCaption(node),
      ...renderColgroup(node),
      ['tbody', 0],
    ];
  },

  // The <caption> is read as an attribute (see renderCaption), and ignored as content: its text would otherwise be
  // wrapped in a row of the table.
  parseHTML() {
    return [...(this.parent?.() ?? []), { tag: 'caption', ignore: true }];
  },

  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: null,
        parseHTML: tableClass,
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
      caption: {
        default: null,
        parseHTML: (element) => element.querySelector(':scope > caption')?.textContent.trim() || null,
        renderHTML: () => ({}),
      },
    };
  },
});

const TEXT_ALIGNS = ['left', 'center', 'right', 'justify'];

// Only a width in pixels is read: the parent's parseInt would turn a `50%` into 50px, silently.
// A <col width> comes first. In legacy import mode, the cells of the first row also get the width the other editor
// wrote on the cells of their column (see widthsFromCells).
function parseColwidth(element, legacyStyles) {
  const colwidth = element.getAttribute('colwidth');
  if (colwidth) {
    return colwidth.split(',').map((width) => parseInt(width, 10));
  }

  const row = element.parentElement;
  const table = element.closest('table');
  if (!row || !table) {
    return null;
  }

  const first = Array.from(row.children)
    .slice(0, Array.from(row.children).indexOf(element))
    .reduce((count, cell) => count + (parseInt(cell.getAttribute('colspan'), 10) || 1), 0);
  const fromCells = legacyStyles && row === table.rows[0] ? widthsFromCells(table) : [];
  const widths = Array.from({ length: parseInt(element.getAttribute('colspan'), 10) || 1 }, (_, i) => (
    colgroupWidth(table, first + i) || fromCells[first + i] || 0
  ));

  return widths.some(Boolean) ? widths : null;
}

// What another editor wrote on the cell becomes the classes the schema uses, unless the cell has one already: the
// alignment (`style="text-align:center"`, `align="center"`) and, in legacy import mode, the background color
// (`style="background-color:#eee"`, looked up in the palette).
function cellClass(element, legacyStyles) {
  const classes = (HtmlCleaner.parseClass(element) ?? '').split(' ').filter(Boolean);
  const align = (element.style?.textAlign || element.getAttribute('align') || '').toLowerCase();

  if (TEXT_ALIGNS.includes(align) && !classes.some((c) => c.startsWith('ut-text-'))) {
    classes.push(`ut-text-${align}`);
  }

  const background = classes.some((c) => c.startsWith('ut-bg-'))
    ? null
    : resolveLegacyClass(legacyStyles, 'backgroundColor', element);

  if (background) {
    classes.push(background);
  }

  return classes.join(' ') || null;
}

function withoutInlineStyles(parentAttrs, legacyStyles) {
  const attrs = { ...parentAttrs };
  // colwidth renders as style="width:Xpx; min-width:Ypx" — neutralize it for CSP compliance
  if (attrs.colwidth) {
    attrs.colwidth = {
      ...attrs.colwidth,
      parseHTML: (element) => parseColwidth(element, legacyStyles),
      renderHTML: () => ({}),
    };
  }
  // The native alignment renders as style="text-align:…": the class (see cellClass) carries it instead
  if (attrs.align) {
    attrs.align = { ...attrs.align, renderHTML: () => ({}) };
  }
  return attrs;
}

export const CustomTableCell = TableCell.extend({
  addOptions() {
    return { ...this.parent?.(), legacyStyles: null };
  },

  addAttributes() {
    return {
      ...withoutInlineStyles(this.parent?.() ?? {}, this.options.legacyStyles),
      class: {
        default: null,
        parseHTML: (element) => cellClass(element, this.options.legacyStyles),
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },
});

export const CustomTableHeader = TableHeader.extend({
  addOptions() {
    return { ...this.parent?.(), legacyStyles: null };
  },

  addAttributes() {
    return {
      ...withoutInlineStyles(this.parent?.() ?? {}, this.options.legacyStyles),
      class: {
        default: null,
        parseHTML: (element) => cellClass(element, this.options.legacyStyles),
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },
});
