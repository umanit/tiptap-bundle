import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
// Opening a dropdown closes the others: their outside click is stopped by the toggle, so they are told apart.
const OPEN_EVENT = 'umanit-tiptap:dropdown-open';

export default class extends Controller {
  static targets = ['button'];

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
    if (event.key !== 'Escape') {
      return;
    }

    // The focus goes back to the button, unless it was elsewhere (it would be lost in the closed menu)
    const hadFocus = this.element.contains(document.activeElement);

    this.close();

    if (hadFocus && this.hasButtonTarget) {
      this.buttonTarget.focus();
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
    this.#setExpanded(true);
    document.addEventListener(OPEN_EVENT, this.#onOtherOpen);
    document.addEventListener('click', this.#onOutsideClick);
    document.addEventListener('keydown', this.#onKeydown);
  }

  close() {
    this.element.removeAttribute('data-open');
    this.#setExpanded(false);
    document.removeEventListener(OPEN_EVENT, this.#onOtherOpen);
    document.removeEventListener('click', this.#onOutsideClick);
    document.removeEventListener('keydown', this.#onKeydown);
  }

  /**
   * Assistive technologies read the state of the menu from its button.
   *
   * @param {boolean} expanded
   */
  #setExpanded(expanded) {
    if (this.hasButtonTarget) {
      this.buttonTarget.setAttribute('aria-expanded', String(expanded));
    }
  }
}
