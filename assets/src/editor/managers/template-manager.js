import AbstractManager from './abstract-manager.js';

export default class TemplateManager extends AbstractManager {
  open() {
    if (!this._formElement) {
      return;
    }

    this._formElement.reset();
    this.openModal();
  }

  /**
   * Inserts the template chosen in the form, if any.
   */
  confirm() {
    const selected = this._formElement?.querySelector('input[name="template"]:checked');

    if (selected) {
      this.insert(selected.value);
    }
  }

  /**
   * @param {string} contentHTML
   */
  insert(contentHTML) {
    if (!this._formElement) {
      return;
    }

    const shouldReplace = this._formElement.elements.replaceContent?.checked;

    const chain = this.chain();

    if (shouldReplace) {
      chain.setContent(contentHTML);
    } else {
      chain.insertContent(contentHTML);
    }

    chain.run();
    this.closeModal();
  }
}
