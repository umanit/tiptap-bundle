import { Controller } from '@hotwired/stimulus';
import { MODAL_PROPERTY } from '../constants.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  initialize() {
    // Exposes the instance so that abstract-manager.js can call it directly
    this.element[MODAL_PROPERTY] = this;
  }

  connect() {
    // Dispatches an event when the dialog is closed (Escape or close())
    this.element.addEventListener('close', () => this.dispatch('closed'));
  }

  disconnect() {
    delete this.element[MODAL_PROPERTY];
  }

  open() {
    this.element.showModal();
    this.dispatch('opened');
  }

  close() {
    this.element.close();
  }
}
