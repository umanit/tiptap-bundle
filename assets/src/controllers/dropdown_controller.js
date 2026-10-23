import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
// Opening a dropdown closes the others: their outside click is stopped by the toggle, so they are told apart.
const OPEN_EVENT = 'umanit-tiptap:dropdown-open';

export default class extends Controller {
  #onOutsideClick = (event) => {
    if (!this.element.contains(event.target)) {
      this.close();
    }
  };

  #onOtherOpen = (event) => {
    if (event.detail.dropdown !== this.element) {
      this.close();
    }
  };

  #onKeydown = (event) => {
    if (event.key === 'Escape') {
      this.close();
    }
  };

  #onItemClick = (event) => {
    if (!this.isOpen) {
      return;
    }
    const target = event.target.closest('button, a, [role="button"]');
    if (!target) {
      return;
    }
    // Don't auto-close when clicking the toggle button (toggle() handles that)
    const isToggle = target.dataset.action?.includes(`${this.identifier}#toggle`);
    if (!isToggle) {
      requestAnimationFrame(() => this.close());
    }
  };

  connect() {
    this.element.addEventListener('click', this.#onItemClick);
  }

  disconnect() {
    this.close();
    this.element.removeEventListener('click', this.#onItemClick);
  }

  get isOpen() {
    return this.element.hasAttribute('data-open');
  }

  toggle(event) {
    event.stopPropagation();
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  open() {
    document.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: { dropdown: this.element } }));
    this.element.setAttribute('data-open', '');
    document.addEventListener(OPEN_EVENT, this.#onOtherOpen);
    document.addEventListener('click', this.#onOutsideClick);
    document.addEventListener('keydown', this.#onKeydown);
  }

  close() {
    this.element.removeAttribute('data-open');
    document.removeEventListener(OPEN_EVENT, this.#onOtherOpen);
    document.removeEventListener('click', this.#onOutsideClick);
    document.removeEventListener('keydown', this.#onKeydown);
  }
}
