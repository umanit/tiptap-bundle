import { MODAL_PROPERTY } from '../../constants.js';

/**
 * Base class of the TipTap managers.
 * Framework-agnostic: it only receives HTMLElement instances.
 */
export default class AbstractManager {
  /** @type {import('@tiptap/core').Editor} */
  _editor;

  /** @type {HTMLFormElement|null} */
  _formElement = null;

  /**
   * @param {import('@tiptap/core').Editor} editor
   * @param {HTMLFormElement|null} formElement
   */
  constructor(editor, formElement = null) {
    this._editor = editor;
    this._formElement = formElement;
  }

  /**
   * @param {HTMLFormElement} form
   *
   * @returns {Object<string, string>}
   */
  getFormData(form) {
    return Object.fromEntries(new FormData(form).entries());
  }

  /**
   * @param {HTMLFormElement} form
   * @param {Object<string, any>} values
   */
  setFormValues(form, values) {
    Object.entries(values).forEach(([name, value]) => {
      const input = form.elements[name];
      if (!input) {
        return;
      }

      if (input.type === 'checkbox') {
        input.checked = Boolean(value);
      } else {
        input.value = value ?? '';
      }
    });
  }

  _openModal = (element) => element?.closest('dialog')?.[MODAL_PROPERTY]?.open();

  _closeModal = (element) => element?.closest('dialog')?.[MODAL_PROPERTY]?.close();

  /**
   * @param {HTMLElement} element
   */
  openModal(element = this._formElement) {
    this._openModal(element);
  }

  /**
   * @param {HTMLElement} element
   */
  closeModal(element = this._formElement) {
    this._closeModal(element);
  }

  /**
   * @param {string} eventName
   * @param {Object} detail
   */
  dispatch(eventName, detail = {}) {
    this._editor.view.dom.dispatchEvent(
      new CustomEvent(`tiptap:${eventName}`, {
        detail: { manager: this.constructor.name, ...detail },
        bubbles: true,
      }),
    );
  }

  /**
   * @returns {import('@tiptap/core').ChainedCommands}
   */
  chain() {
    return this._editor.chain().focus();
  }
}
