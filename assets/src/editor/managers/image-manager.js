import AbstractManager from './abstract-manager.js';

/**
 * @typedef {((onSelect: (url: string) => void) => void)|null} FileManagerAdapter
 */

const MARGIN_CLASSES = ['ut-ma-5', 'ut-ma-10'];
const ALIGN_CLASSES = [['ut-float-start', 'left'], ['ut-float-end', 'right'], ['ut-mx-auto', 'center']];
const MANAGED_CLASSES = ['ut-float-start', 'ut-float-end', 'ut-block', 'ut-mx-auto', ...MARGIN_CLASSES];

export default class ImageManager extends AbstractManager {
  /** @type {string[]} Classes of the edited image that the form does not manage, kept as they are */
  #extraClasses = [];

  /** @type {FileManagerAdapter|null} */
  #fileManagerAdapter = null;

  /**
   * @param {import('@tiptap/core').Editor} editor
   * @param {Object} options
   * @param {HTMLFormElement|null} options.form
   * @param {FileManagerAdapter} options.fileManagerAdapter
   * @param {Object} modal
   */
  constructor(editor, { form = null, fileManagerAdapter = null } = {}, modal = {}) {
    super(editor, form, modal);

    this.#fileManagerAdapter = fileManagerAdapter;
  }

  open() {
    if (!this._formElement) {
      return;
    }

    this._formElement.reset();
    this.#extraClasses = [];

    // An image is selected: edit it instead of inserting a new one
    if (this._editor.isActive('image')) {
      const {
        src, alt, width, height, class: className,
      } = this._editor.getAttributes('image');
      const classes = (className ?? '').split(/\s+/).filter(Boolean);
      const align = ALIGN_CLASSES.find(([name]) => classes.includes(name))?.[1] ?? '';

      this.#extraClasses = classes.filter((name) => !MANAGED_CLASSES.includes(name));
      this.setFormValues(this._formElement, {
        url: src,
        alt,
        width,
        height,
        align,
        margin: classes.find((name) => MARGIN_CLASSES.includes(name)) ?? '',
      });
      // Refreshes the preview
      this._formElement.querySelectorAll('input, select').forEach((field) => {
        field.dispatchEvent(new Event(field.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
      });
    }

    this.openModal();
  }

  confirm() {
    if (!this._formElement) {
      return;
    }

    const data = this.getFormData(this._formElement);

    if (data.url) {
      const classes = [...this.#extraClasses];

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
