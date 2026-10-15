import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ['url', 'alt', 'width', 'height', 'align', 'margin', 'previewImage'];

  /** @type {AbortController} */
  #abortController;

  connect() {
    this.#abortController = new AbortController();
    this.#setListeners();
  }

  disconnect() {
    this.#abortController.abort();
  }

  reset() {
    this.urlTarget.value = '';
    this.altTarget.value = '';
    this.widthTarget.value = '';
    this.heightTarget.value = '';
    this.alignTarget.selectedIndex = 0;
    this.marginTarget.selectedIndex = 0;

    this.#updatePreview();
  }

  #updatePreview() {
    const img = this.previewImageTarget;

    img.classList.toggle('ut-hidden', !this.urlTarget.value);
    img.src = this.urlTarget.value;
    img.alt = this.altTarget.value;
    img.style.width = this.widthTarget.value ? `${this.widthTarget.value}px` : '';
    img.style.height = this.heightTarget.value ? `${this.heightTarget.value}px` : '';

    img.classList.remove('ut-float-start', 'ut-float-end', 'ut-block', 'ut-mx-auto');
    if (this.alignTarget.value === 'left') img.classList.add('ut-float-start');
    else if (this.alignTarget.value === 'right') img.classList.add('ut-float-end');
    else if (this.alignTarget.value === 'center') img.classList.add('ut-block', 'ut-mx-auto');

    img.classList.remove('ut-ma-5', 'ut-ma-10');
    if (this.marginTarget.value) img.classList.add(this.marginTarget.value);
  }

  #setListeners() {
    const { signal } = this.#abortController;

    this.urlTarget.addEventListener('input', () => this.#updatePreview(), { signal });
    this.altTarget.addEventListener('input', () => this.#updatePreview(), { signal });
    this.widthTarget.addEventListener('input', () => this.#updatePreview(), { signal });
    this.heightTarget.addEventListener('input', () => this.#updatePreview(), { signal });
    this.alignTarget.addEventListener('change', () => this.#updatePreview(), { signal });
    this.marginTarget.addEventListener('change', () => this.#updatePreview(), { signal });
  }
}
