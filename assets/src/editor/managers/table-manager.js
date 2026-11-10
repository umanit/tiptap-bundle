import { selectedRect } from '@tiptap/pm/tables';
import AbstractManager from './abstract-manager.js';

// Classes of a cell which the cell form manages: any other one is left as it is
const CELL_FORM_CLASS = /^(ut-bg-.+|ut-align-.+|ut-text-(left|center|right|justify))$/;
const TABLE_ALIGN_CLASSES = ['ut-table-center', 'ut-table-right'];
// Keys moving the focus through the entries of the context menu, to the next one (+1), the previous one (-1)…
const MENU_MOVES = {
  ArrowDown: 1, ArrowUp: -1, Home: -Infinity, End: Infinity,
};

export default class TableManager extends AbstractManager {
  /** @type {HTMLFormElement|null} */
  #cellFormElement = null;

  /** @type {HTMLElement|null} */
  #contextMenuElement = null;

  /** @type {boolean} */
  #isEditing = false;

  /** @type {AbortController} */
  #abortController = new AbortController();

  /**
   * @param {import('@tiptap/core').Editor} editor
   * @param {Object} elements
   * @param {HTMLFormElement|null} elements.tableForm
   * @param {HTMLFormElement|null} elements.cellForm
   * @param {HTMLElement|null} elements.contextMenu
   * @param {Object} modal
   */
  constructor(editor, { tableForm = null, cellForm = null, contextMenu = null } = {}, modal = {}) {
    super(editor, tableForm, modal);

    this.#cellFormElement = cellForm;
    this.#contextMenuElement = contextMenu;

    const { signal } = this.#abortController;

    this.#contextMenuElement?.addEventListener('keydown', (event) => this.#navigateContextMenu(event), { signal });
    // The browser menu stays closed over the one of the table (the Menu key fires it on the focused entry)
    this.#contextMenuElement?.addEventListener('contextmenu', (event) => event.preventDefault(), { signal });
  }

  destroy() {
    this.#abortController.abort();
  }

  handleContextMenu(event) {
    if (event.target.closest('td, th')) {
      event.preventDefault();
      this.#showContextMenu(event.clientX, event.clientY);

      return;
    }

    // A context menu asked from the keyboard (a screen reader command, a Menu key left to the browser) targets the
    // focused editing area, not a cell: it opens under the cursor when the cursor is in a table.
    if (event.target === this._editor.view.dom && this.showContextMenuAtSelection()) {
      event.preventDefault();

      return;
    }

    this.hideContextMenu();
  }

  /**
   * Opens the context menu from the keyboard, under the cursor, when it is in a table.
   *
   * @returns {boolean} Whether the menu opened
   */
  showContextMenuAtSelection() {
    if (!this.#contextMenuElement || !this._editor.isActive('table')) {
      return false;
    }

    const { left, bottom } = this._editor.view.coordsAtPos(this._editor.state.selection.from);

    this.#showContextMenu(left, bottom);
    this.#menuItems()[0]?.focus();

    return true;
  }

  hideContextMenu() {
    if (!this.#contextMenuElement) {
      return;
    }

    // The focus would be lost in the hidden menu: it goes back to the cursor in the table
    if (this.#contextMenuElement.contains(document.activeElement)) {
      this._editor.commands.focus();
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
      cssClass,
      caption: attrs.caption || '',
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

  /**
   * @returns {HTMLElement[]}
   */
  #menuItems() {
    return [...this.#contextMenuElement.querySelectorAll('[role="menuitem"]')];
  }

  /**
   * The arrows, Home and End move through the entries; Escape and Tab close the menu.
   *
   * @param {KeyboardEvent} event
   */
  #navigateContextMenu(event) {
    if (event.key === 'Escape' || event.key === 'Tab') {
      event.preventDefault();
      this.hideContextMenu();

      return;
    }

    const move = MENU_MOVES[event.key];

    if (undefined === move) {
      return;
    }

    event.preventDefault();

    const items = this.#menuItems();
    const current = items.indexOf(document.activeElement);
    // From outside the entries, down goes to the first one and up to the last one
    const from = current === -1 && move < 0 ? 0 : current;
    const next = Number.isFinite(move)
      ? (from + move + items.length) % items.length
      : Math.min(Math.max(move, 0), items.length - 1);

    items[next]?.focus();
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

  /**
   * Every attribute is given, `null` to remove it: updateAttributes() keeps the ones it is not told about.
   */
  #buildTableAttributes(data) {
    const classes = [data.tableWidth, data.tableAlign, data.cssClass].filter(Boolean).join(' ');

    return { class: classes || null, caption: data.caption?.trim() || null };
  }
}
