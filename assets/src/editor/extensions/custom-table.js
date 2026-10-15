import { Table, TableCell, TableHeader } from '@tiptap/extension-table';
import { mergeAttributes } from '@tiptap/core';

export const CustomTable = Table.extend({
  // Override renderHTML to prevent the parent from generating:
  // - style="min-width: Xpx" on <table>
  // - <colgroup><col style="min-width: Xpx"></colgroup>
  renderHTML({ HTMLAttributes }) {
    return ['table', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), ['tbody', 0]];
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

function withoutInlineStyles(parentAttrs) {
  const attrs = { ...parentAttrs };
  // colwidth renders as style="width:Xpx; min-width:Ypx" — neutralize it for CSP compliance
  if (attrs.colwidth) {
    attrs.colwidth = { ...attrs.colwidth, renderHTML: () => ({}) };
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
