import { Table, TableCell, TableHeader } from '@tiptap/extension-table';
import { mergeAttributes } from '@tiptap/core';

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
    this.#syncColgroup();
  }

  update(node) {
    if (node.type !== this.node.type) {
      return false;
    }

    // Another class or border: let ProseMirror build the view again
    if (node.attrs.class !== this.node.attrs.class || node.attrs.border !== this.node.attrs.border) {
      return false;
    }

    this.node = node;
    this.#syncColgroup();

    return true;
  }

  ignoreMutation(mutation) {
    return !this.contentDOM.contains(mutation.target);
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
      ...renderColgroup(node),
      ['tbody', 0],
    ];
  },

  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: null,
        parseHTML: (element) => element.getAttribute('class'),
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
      border: {
        default: null,
        parseHTML: (element) => element.getAttribute('border'),
        renderHTML: (attributes) => (attributes.border ? { border: attributes.border } : {}),
      },
    };
  },
});

// Only a width in pixels is read: the parent's parseInt would turn a `50%` into 50px, silently.
function parseColwidth(element) {
  const colwidth = element.getAttribute('colwidth');
  if (colwidth) {
    return colwidth.split(',').map((width) => parseInt(width, 10));
  }

  const row = element.parentElement;
  const cols = element.closest('table')?.querySelectorAll('colgroup > col');
  if (!row || !cols?.length) {
    return null;
  }

  const first = Array.from(row.children)
    .slice(0, Array.from(row.children).indexOf(element))
    .reduce((count, cell) => count + (parseInt(cell.getAttribute('colspan'), 10) || 1), 0);
  const widths = Array.from({ length: parseInt(element.getAttribute('colspan'), 10) || 1 }, (_, i) => {
    const width = cols[first + i]?.getAttribute('width') ?? '';

    return /^\d+(px)?$/.test(width) ? parseInt(width, 10) : 0;
  });

  return widths.some(Boolean) ? widths : null;
}

function withoutInlineStyles(parentAttrs) {
  const attrs = { ...parentAttrs };
  // colwidth renders as style="width:Xpx; min-width:Ypx" — neutralize it for CSP compliance
  if (attrs.colwidth) {
    attrs.colwidth = { ...attrs.colwidth, parseHTML: parseColwidth, renderHTML: () => ({}) };
  }
  return attrs;
}

export const CustomTableCell = TableCell.extend({
  addAttributes() {
    return {
      ...withoutInlineStyles(this.parent?.() ?? {}),
      class: {
        default: null,
        parseHTML: (element) => element.getAttribute('class') || null,
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },
});

export const CustomTableHeader = TableHeader.extend({
  addAttributes() {
    return {
      ...withoutInlineStyles(this.parent?.() ?? {}),
      class: {
        default: null,
        parseHTML: (element) => element.getAttribute('class') || null,
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },
});
