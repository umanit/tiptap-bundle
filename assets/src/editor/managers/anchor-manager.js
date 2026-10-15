import AbstractManager from './abstract-manager.js';

export default class AnchorManager extends AbstractManager {
  open() {
    if (!this._formElement) {
      return;
    }

    const { parent } = this._editor.state.selection.$from;
    this.setFormValues(this._formElement, { id: parent.attrs.id || '' });
    this.openModal();
  }

  confirm() {
    if (!this._formElement) {
      return;
    }

    const { id } = this.getFormData(this._formElement);
    const { parent } = this._editor.state.selection.$from;
    const trimmedId = id?.trim() || null;

    this._editor.chain().focus().updateAttributes(parent.type.name, { id: trimmedId }).run();
    this.closeModal();

    if (trimmedId) {
      this.dispatch('anchor:inserted', { id: trimmedId });
    }
  }

  remove() {
    const { parent } = this._editor.state.selection.$from;

    this._editor.chain().focus().updateAttributes(parent.type.name, { id: null }).run();
  }
}
