import { selectedRect } from '@tiptap/pm/tables';
import AbstractManager from './abstract-manager.js';

// Classes of a cell which the cell form manages: any other one is left as it is
const CELL_FORM_CLASS = /^(ut-bg-.+|ut-align-.+|ut-text-(left|center|right|justify))$/;
const TABLE_ALIGN_CLASSES = ['ut-table-center', 'ut-table-right'];

export default class TableManager extends AbstractManager {
  /** @type {HTMLFormElement|null} */
  #cellFormElement = null;

  /** @type {HTMLElement|null} */
  #contextMenuElement = null;

  /** @type {boolean} */
  #isEditing = false;

  /**
   * @param {import('@tiptap/core').Editor} editor
   * @param {Object} elements
   * @param {HTMLFormElement|null} elements.tableForm
   * @param {HTMLFormElement|null} elements.cellForm
   * @param {HTMLElement|null} elements.contextMenu
   */
  constructor(editor, { tableForm = null, cellForm = null, contextMenu = null } = {}) {
    super(editor, tableForm);

    this.#cellFormElement = cellForm;
    this.#contextMenuElement = contextMenu;
  }

  handleContextMenu(event) {
    const target = event.target.closest('td, th');

    if (target) {
      event.preventDefault();
      this.#showContextMenu(event.clientX, event.clientY);
    } else {
      this.hideContextMenu();
    }
  }

  hideContextMenu() {
    if (!this.#contextMenuElement) {
      return;
    }

    this.#contextMenuElement.classList.add('ut-hidden');
  }

  openAddModal() {
    if (!this._formElement) {
      return;
    }

    this.#isEditing = false;
    this._formElement.reset();
    this.#toggleStructureSection(true);
    this.openModal();
  }

  openEditModal() {
    if (!this._formElement) {
      return;
    }

    this.#isEditing = true;
    this.hideContextMenu();
    this.#toggleStructureSection(false);

    const attrs = this._editor.getAttributes('table');
    const classes = (attrs.class || '').split(' ');
    const tableWidth = classes.find((c) => c.startsWith('ut-w-')) || '';
    const tableAlign = classes.find((c) => TABLE_ALIGN_CLASSES.includes(c)) || '';
    const cssClass = classes.filter((c) => !c.startsWith('ut-w-') && !TABLE_ALIGN_CLASSES.includes(c)).join(' ');

    this.setFormValues(this._formElement, {
      tableWidth,
      tableAlign,
      border: attrs.border || '',
      cssClass,
    });

    this.openModal();
  }

  confirmAdd() {
    if (!this._formElement) {
      return;
    }

    const data = this.getFormData(this._formElement);

    if (this.#isEditing) {
      this.chain()
        .updateAttributes('table', this.#buildTableAttributes(data))
        .run();
      this.closeModal();
      return;
    }

    const rows = parseInt(data.rows, 10) || 3;
    const cols = parseInt(data.cols, 10) || 2;
    const withHeaderRow = data.withHeader === '1';

    this.chain()
      .insertTable({ rows, cols, withHeaderRow })
      .updateAttributes('table', this.#buildTableAttributes(data))
      .run();

    this.closeModal();
    this.dispatch('table:created', { rows, cols });
  }

  delete() {
    this.chain().deleteTable().run();
    this.hideContextMenu();
    this.dispatch('table:deleted');
  }

  openCellModal() {
    this.hideContextMenu();

    if (!this.#cellFormElement) {
      return;
    }

    let attrs = this._editor.getAttributes('tableCell');

    if (!attrs.class) {
      const headerAttrs = this._editor.getAttributes('tableHeader');
      attrs = { ...attrs, ...headerAttrs };
    }

    const classes = (attrs.class || '').split(' ').filter(Boolean);
    const bgClass = classes.find((c) => /^ut-bg-.+/.test(c)) || '';
    const alignClass = classes.find((c) => c.startsWith('ut-align-')) || '';
    const textAlignClass = classes.find((c) => c.startsWith('ut-text-')) || '';

    this.setFormValues(this.#cellFormElement, {
      bgColor: bgClass,
      verticalAlign: alignClass,
      textAlign: textAlignClass,
      colWidth: this.#selectedColumnWidth() ?? '',
    });

    this.openModal(this.#cellFormElement);
  }

  confirmUpdateCell() {
    if (!this.#cellFormElement) {
      return;
    }

    const data = this.getFormData(this.#cellFormElement);
    const formClasses = [data.bgColor, data.verticalAlign, data.textAlign].filter(Boolean);

    this.chain()
      .command(({ tr, state }) => this.#setCellClasses(tr, state, formClasses))
      .command(({ tr, state }) => this.#setColumnWidth(tr, state, parseInt(data.colWidth, 10)))
      .run();

    this.closeModal(this.#cellFormElement);
  }

  mergeCells() {
    this.chain().mergeCells().run();
    this.hideContextMenu();
  }

  splitCell() {
    this.chain().splitCell().run();
    this.hideContextMenu();
  }

  addRowBefore() {
    this.chain().addRowBefore().run();
    this.hideContextMenu();
  }

  addRowAfter() {
    this.chain().addRowAfter().run();
    this.hideContextMenu();
  }

  getContextMenuElement() {
    return this.#contextMenuElement;
  }

  #toggleStructureSection(visible) {
    this._formElement?.querySelectorAll('[data-table-structure]').forEach((el) => {
      el.classList.toggle('ut-hidden', !visible);
    });
  }

  #showContextMenu(x, y) {
    if (!this.#contextMenuElement) {
      return;
    }

    Object.assign(this.#contextMenuElement.style, {
      left: `${x}px`,
      top: `${y}px`,
      zIndex: '1060',
    });
    this.#contextMenuElement.classList.remove('ut-hidden');
  }

  /**
   * Gives every selected cell the classes of the form, keeping the other classes it has.
   *
   * @param {import('@tiptap/pm/state').Transaction} tr
   * @param {import('@tiptap/pm/state').EditorState} state
   * @param {string[]} formClasses
   * @returns {boolean}
   */
  #setCellClasses(tr, state, formClasses) {
    const {
      left, right, top, bottom, map, table, tableStart,
    } = selectedRect(state);

    map.cellsInRect({
      left, right, top, bottom,
    }).forEach((pos) => {
      const cell = table.nodeAt(pos);
      const kept = (cell.attrs.class || '').split(' ').filter((c) => c && !CELL_FORM_CLASS.test(c));
      const classes = [...kept, ...formClasses].join(' ');

      tr.setNodeMarkup(tableStart + pos, null, { ...cell.attrs, class: classes || null });
    });

    return true;
  }

  /**
   * Width (in px) of the first selected column, null when it has none.
   *
   * @returns {number|null}
   */
  #selectedColumnWidth() {
    const {
      left, map, table,
    } = selectedRect(this._editor.state);
    const pos = map.map[left];
    const cell = table.nodeAt(pos);

    return cell.attrs.colwidth?.[left - map.colCount(pos)] || null;
  }

  /**
   * Gives the selected columns the width (in px, none when not a positive number) on every one of their cells.
   *
   * @param {import('@tiptap/pm/state').Transaction} tr
   * @param {import('@tiptap/pm/state').EditorState} state
   * @param {number} width
   * @returns {boolean}
   */
  #setColumnWidth(tr, state, width) {
    const {
      left, right, map, table, tableStart,
    } = selectedRect(state);
    const value = width > 0 ? width : 0;
    const done = new Set();

    for (let row = 0; row < map.height; row += 1) {
      for (let col = left; col < right; col += 1) {
        const pos = map.map[row * map.width + col];

        if (!done.has(pos)) {
          done.add(pos);

          const cell = table.nodeAt(pos);
          const first = map.colCount(pos);
          // A cell spanning several columns keeps the widths of those which are not selected
          const colwidth = Array.from({ length: cell.attrs.colspan }, (_, i) => (
            first + i >= left && first + i < right ? value : (cell.attrs.colwidth?.[i] ?? 0)
          ));

          tr.setNodeMarkup(tableStart + pos, null, {
            ...cell.attrs,
            colwidth: colwidth.some(Boolean) ? colwidth : null,
          });
        }
      }
    }

    return true;
  }

  #buildTableAttributes(data) {
    const attributes = {};

    if (data.border && parseInt(data.border, 10) > 0) {
      attributes.border = data.border;
    }

    const classes = [data.tableWidth, data.tableAlign, data.cssClass].filter(Boolean).join(' ');
    if (classes) {
      attributes.class = classes;
    }

    return attributes;
  }
}
