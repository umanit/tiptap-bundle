import AbstractManager from './abstract-manager.js';

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
    const cssClass = classes.filter((c) => !c.startsWith('ut-w-')).join(' ');

    this.setFormValues(this._formElement, {
      tableWidth,
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
    const bgClass = classes.find((c) => c.startsWith('ut-bg-')) || '';
    const alignClass = classes.find((c) => c.startsWith('ut-align-')) || '';

    this.setFormValues(this.#cellFormElement, {
      bgColor: bgClass,
      verticalAlign: alignClass,
    });

    this.openModal(this.#cellFormElement);
  }

  confirmUpdateCell() {
    if (!this.#cellFormElement) {
      return;
    }

    const data = this.getFormData(this.#cellFormElement);
    const classes = [data.bgColor, data.verticalAlign].filter(Boolean).join(' ');
    const attributes = { class: classes || null };

    this.chain()
      .updateAttributes('tableCell', attributes)
      .updateAttributes('tableHeader', attributes)
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

  #buildTableAttributes(data) {
    const attributes = {};

    if (data.border && parseInt(data.border, 10) > 0) {
      attributes.border = data.border;
    }

    const classes = [data.tableWidth, data.cssClass].filter(Boolean).join(' ');
    if (classes) {
      attributes.class = classes;
    }

    return attributes;
  }
}
