import AbstractManager from './abstract-manager.js';

/**
 * @typedef {((onSelect: (url: string) => void) => void)|null} FileManagerAdapter
 */

export default class ImageManager extends AbstractManager {
  /** @type {FileManagerAdapter|null} */
  #fileManagerAdapter = null;

  /**
   * @param {import('@tiptap/core').Editor} editor
   * @param {Object} options
   * @param {HTMLFormElement|null} options.form
   * @param {FileManagerAdapter} options.fileManagerAdapter
   */
  constructor(editor, { form = null, fileManagerAdapter = null } = {}) {
    super(editor, form);

    this.#fileManagerAdapter = fileManagerAdapter;
  }

  open() {
    if (!this._formElement) {
      return;
    }

    this._formElement.reset();
    this.openModal();
  }

  confirm() {
    if (!this._formElement) {
      return;
    }

    const data = this.getFormData(this._formElement);

    if (data.url) {
      const classes = [];

      if (data.align === 'left') {
        classes.push('ut-float-start');
      } else if (data.align === 'right') {
        classes.push('ut-float-end');
      } else if (data.align === 'center') {
        classes.push('ut-block', 'ut-mx-auto');
      }

      if (data.margin) {
        classes.push(data.margin);
      }

      this.chain().setImage({
        src: data.url,
        alt: data.alt || '',
        width: data.width || null,
        height: data.height || null,
        class: classes.length ? classes.join(' ') : null,
      }).run();
    }

    this.closeModal();
    this.dispatch('image:inserted', { ...data });
  }

  openFileManager() {
    if (!this.#fileManagerAdapter) {
      throw new Error('Missing "fileManagerAdapter", cannot open the file manager.');
    }

    this.#fileManagerAdapter((url) => {
      const input = this._formElement?.querySelector('[name="url"]');
      if (input) {
        input.value = url;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }
}
