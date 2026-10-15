import AbstractManager from './abstract-manager.js';

export default class LinkManager extends AbstractManager {
  /** @type {HTMLElement|null} */
  #urlGroupElement = null;

  /** @type {HTMLElement|null} */
  #anchorGroupElement = null;

  /**
   * @param {import('@tiptap/core').Editor} editor
   * @param {Object} elements
   * @param {HTMLFormElement|null} elements.form
   * @param {HTMLElement|null} elements.urlGroup
   * @param {HTMLElement|null} elements.anchorGroup
   */
  constructor(editor, { form = null, urlGroup = null, anchorGroup = null } = {}) {
    super(editor, form);

    this.#urlGroupElement = urlGroup;
    this.#anchorGroupElement = anchorGroup;
  }

  toggleType() {
    if (!this._formElement) {
      return;
    }

    const data = this.getFormData(this._formElement);
    const isUrl = data.type === 'url';

    this.#urlGroupElement?.classList.toggle('ut-hidden', !isUrl);
    this.#anchorGroupElement?.classList.toggle('ut-hidden', isUrl);
  }

  open() {
    if (!this._formElement) {
      return;
    }

    const attrs = this._editor.getAttributes('link');
    const href = attrs.href || '';
    const isAnchor = href.startsWith('#');

    this.setFormValues(this._formElement, {
      type: isAnchor ? 'anchor' : 'url',
      url: isAnchor ? '' : href,
      anchor: isAnchor ? href.substring(1) : '',
      target: attrs.target || '',
    });

    this.toggleType();
    this.openModal();
  }

  confirm() {
    if (!this._formElement) {
      return;
    }

    const data = this.getFormData(this._formElement);
    const { url, isAnchor } = this.#buildUrl(data);

    if (!url) {
      this.unset();
    } else {
      this.chain()
        .extendMarkRange('link')
        .setLink({
          href: url,
          target: data.target || null,
          rel: isAnchor ? null : undefined,
        })
        .run();
    }

    this.closeModal();
    this.dispatch('link:updated', { url, isAnchor });
  }

  unset() {
    this.chain().extendMarkRange('link').unsetLink().run();
    this.dispatch('link:removed');
  }

  /**
   * @param {Object} data
   * @returns {{ url: string, isAnchor: boolean }}
   */
  #buildUrl(data) {
    if (data.type === 'anchor' && data.anchor) {
      const anchor = data.anchor.startsWith('#') ? data.anchor : `#${data.anchor}`;
      return { url: anchor, isAnchor: true };
    }

    return { url: data.url || '', isAnchor: false };
  }
}
