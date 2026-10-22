import { Controller } from '@hotwired/stimulus';
import { MODAL_PROPERTY, SET_CONTENT_EVENT } from '../constants.js';
import TipTapEditor from '../editor/tiptap-editor.js';
import HtmlCleaner from '../editor/html-cleaner.js';
import HtmlFormatter from '../editor/html-formatter.js';
import { analyzeContent } from '../editor/migration-diff.js';
import { DEFAULT_ADAPTER_NAME } from '../file-manager/default-adapter.js';
import { createFileManagerAdapter } from '../file-manager/registry.js';
import '../index.js';

const NODE_COMMANDS = { 'image.open': 'image' };
// Commands whose state is not a node or a mark: the editor tells it with a dedicated method
// Commands which set a class on the text: they are active when the selection has the class
const CLASS_COMMANDS = ['setTextColor', 'setTextBgColor', 'setTextSize'];
const STATE_COMMANDS = { toggleListTight: 'isListTight' };

export default class extends Controller {
  static targets = [
    'editor', 'input', 'toolbar', 'contextMenu', 'modals',
    'tableForm', 'cellForm',
    'linkForm', 'linkUrlGroup', 'linkAnchorGroup',
    'imageForm',
    'templateForm',
    'anchorForm',
    'diffBanner', 'diffMessage', 'diffBefore', 'diffAfter', 'diffSignals', 'diffLines',
  ];

  static values = {
    translations: Object,
    fileManagerUrl: String,
    migrationDiff: Boolean,
    fileManagerAdapter: {
      type: String,
      default: DEFAULT_ADAPTER_NAME,
    },
  };

  /** @var {TipTapEditor} */
  #wysiwygEditor;

  /** @var {AbortController} */
  #abortController;

  /** Original content and what the editor made of it, when the migration diff is on. */
  #migration = null;

  connect() {
    this.#abortController = new AbortController();

    this.#mountModals();

    const storedContent = this.inputTarget.value;

    try {
      const fileManagerAdapter = this.hasFileManagerUrlValue
        ? createFileManagerAdapter(this.fileManagerAdapterValue, this.fileManagerUrlValue)
        : null;

      this.#wysiwygEditor = new TipTapEditor({
        tableForm: this.hasTableFormTarget ? this.tableFormTarget : null,
        cellForm: this.hasCellFormTarget ? this.cellFormTarget : null,
        contextMenu: this.hasContextMenuTarget ? this.contextMenuTarget : null,
        linkForm: this.hasLinkFormTarget ? this.linkFormTarget : null,
        linkUrlGroup: this.hasLinkUrlGroupTarget ? this.linkUrlGroupTarget : null,
        linkAnchorGroup: this.hasLinkAnchorGroupTarget ? this.linkAnchorGroupTarget : null,
        imageForm: this.hasImageFormTarget ? this.imageFormTarget : null,
        templateForm: this.hasTemplateFormTarget ? this.templateFormTarget : null,
        anchorForm: this.hasAnchorFormTarget ? this.anchorFormTarget : null,
      }, {
        element: this.editorTarget,
        content: this.inputTarget.value || null,
        editorProps: {
          transformPastedHTML: (html) => HtmlCleaner.normalizeWordHtml(html),
        },
        onUpdate: ({ editor }) => {
          this.inputTarget.value = editor.getHTML();
          this.#updateToolbarState();
        },
        onSelectionUpdate: () => this.#updateToolbarState(),
      }, {
        placeholder: this.translationsValue.placeholder || null,
      }, fileManagerAdapter, {
        open: (el) => this.openModal(el),
        close: (el) => this.closeModal(el),
      });

      this.#wysiwygEditor.onSourceModeChange((isSourceMode) => this.#updateSourceModeUI(isSourceMode));

      this.#setupEventListeners();
    } catch (err) {
      throw new Error('Cannot instantiate the TipTap editor', { cause: err });
    }

    if (this.migrationDiffValue) {
      this.#checkMigration(storedContent);
    }
  }

  disconnect() {
    this.#abortController.abort();
    this.#wysiwygEditor?.destroy();
  }

  run(event) {
    const { command, args } = event.params;
    this.#wysiwygEditor?.runCommand(command, args);
    this.#updateToolbarState();
  }

  toggleSource() {
    if (!this.#wysiwygEditor) {
      return;
    }
    const newMode = !this.#wysiwygEditor.isSourceMode();

    if (newMode) {
      // The layout is for reading only: what is stored stays compact.
      this.inputTarget.value = HtmlFormatter.format(this.#wysiwygEditor.getHTML());
    } else {
      this.#wysiwygEditor.setContent(this.inputTarget.value);
      this.inputTarget.value = this.#wysiwygEditor.getHTML();
    }

    this.#wysiwygEditor.setSourceMode(newMode);
  }

  openDiff() {
    if (!this.#migration || !this.hasDiffBeforeTarget) {
      return;
    }

    const { before, result } = this.#migration;
    const stylesheets = [...document.querySelectorAll('link[rel="stylesheet"]')]
      .map((link) => link.cloneNode().outerHTML)
      .join('');
    const preview = (html) => `<!doctype html><html><head>${stylesheets}`
      + '<style>body{margin:0}.umanit-tiptap__content{min-height:0;padding:1rem}</style></head>'
      + `<body class="umanit-tiptap__content">${html}</body></html>`;

    // The contents are put in the page as text only: they are not to be trusted
    this.diffSignalsTarget.replaceChildren(...result.lost.map(({ signal, before: was, after: now }) => {
      const item = document.createElement('li');
      item.textContent = `${signal} (${was} → ${now})`;

      return item;
    }));

    const marks = {
      removed: '-', added: '+', same: ' ', gap: ' ',
    };
    this.diffLinesTarget.replaceChildren(...result.lines.map(({ type, line }) => {
      const item = document.createElement('code');
      item.className = `umanit-tiptap__diff-line umanit-tiptap__diff-line--${type}`;
      item.textContent = `${marks[type]} ${line}`;

      return item;
    }));

    this.openModal(this.diffBeforeTarget);

    // A fresh iframe each time, once the dialog is open: one which loaded its `srcdoc` while the closed dialog hid
    // it stays blank when opened again, whatever is assigned to it afterwards.
    [[this.diffBeforeTarget, before], [this.diffAfterTarget, result.after]].forEach(([iframe, html]) => {
      const fresh = iframe.cloneNode(false);
      fresh.removeAttribute('srcdoc');
      iframe.replaceWith(fresh);
      fresh.srcdoc = preview(html);
    });
  }

  openFileManager() {
    this.#wysiwygEditor?.openFileManager();
  }

  /**
   * Opens the modal containing the given element.
   * Override this method to plug another modal system.
   *
   * @param {HTMLElement} element
   */
  openModal(element) {
    element?.closest('dialog')?.[MODAL_PROPERTY]?.open();
  }

  /**
   * Closes the modal containing the given element.
   * Override this method to plug another modal system.
   *
   * @param {HTMLElement} element
   */
  closeModal(element) {
    element?.closest('dialog')?.[MODAL_PROPERTY]?.close();
  }

  /**
   * The modals are shipped in a `<template>`: their forms would otherwise be parsed inside the host form, where
   * the HTML parser drops them and closes the host form too early. Inserting them with the DOM API keeps them nested
   * but isolated: their fields do not belong to the host form.
   */
  #mountModals() {
    if (!this.hasModalsTarget) {
      return;
    }

    const template = this.modalsTarget;

    this.element.append(template.content);
    template.remove();
  }

  #setupEventListeners() {
    const { signal } = this.#abortController;

    this.editorTarget.addEventListener(
      'contextmenu',
      (event) => this.#wysiwygEditor.handleContextMenu(event),
      { signal },
    );

    this.element.addEventListener(
      SET_CONTENT_EVENT,
      (event) => this.#setContent(event.detail?.content ?? ''),
      { signal },
    );

    // The source view shows a laid out HTML: what gets submitted is always the compact one.
    this.element.closest('form')?.addEventListener('submit', () => {
      if (this.#wysiwygEditor.isSourceMode()) {
        this.#setContent(this.inputTarget.value);
      }
    }, { signal });

    document.addEventListener('click', () => this.#wysiwygEditor?.hideContextMenu(), { signal });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.#wysiwygEditor?.hideContextMenu();
      }
    }, { signal });
  }

  /**
   * `setContent` does not emit an update: the textarea, which is what gets submitted, is synchronized by hand.
   *
   * @param {string} html
   */
  #setContent(html) {
    this.#wysiwygEditor.setContent(html);
    this.inputTarget.value = this.#wysiwygEditor.getHTML();
    this.#updateToolbarState();
  }

  /**
   * Compares the stored content with what the editor made of it, and warns when they differ.
   *
   * @param {string} before
   */
  #checkMigration(before) {
    const result = analyzeContent(before, this.#wysiwygEditor.getHTML(), new DOMParser());

    if (!result.changed) {
      return;
    }

    this.#migration = { before, result };

    if (this.hasDiffBannerTarget) {
      const { diffLost = '', diffRewritten = '' } = this.translationsValue;

      this.diffMessageTarget.textContent = result.lost.length > 0
        ? diffLost.replace('{signals}', result.lost.map(({ signal }) => signal).join(', '))
        : diffRewritten;
      this.diffBannerTarget.classList.remove('ut-hidden');
    }
  }

  #updateSourceModeUI(isSourceMode) {
    this.editorTarget.classList.toggle('ut-hidden', isSourceMode);
    this.inputTarget.classList.toggle('ut-hidden', !isSourceMode);
    this.toolbarTarget.classList.toggle('umanit-tiptap__toolbar--disabled', isSourceMode);
  }

  #updateToolbarState() {
    this.toolbarTarget.querySelectorAll('[data-wysiwyg-tool]').forEach((button) => {
      const command = button.getAttribute(`data-${this.identifier}-command-param`);

      // Manager commands have no "isActive" counterpart, except the ones bound to a node
      if (command in NODE_COMMANDS) {
        button.classList.toggle('active', this.#wysiwygEditor.isActive(NODE_COMMANDS[command]));

        return;
      }

      if (command in STATE_COMMANDS) {
        button.classList.toggle('active', this.#wysiwygEditor[STATE_COMMANDS[command]]());

        return;
      }

      if (CLASS_COMMANDS.includes(command)) {
        const className = (button.getAttribute(`data-${this.identifier}-args-param`) ?? '').replace(/^"|"$/g, '');

        button.classList.toggle('active', '' !== className && this.#wysiwygEditor.hasSpanClass(className));

        return;
      }

      if (!command || command.includes('.')) {
        return;
      }

      const name = command.replace(/^(toggle|set)([A-Z])/, (_, __, c) => c.toLowerCase());
      if (name === command) {
        return;
      }

      const rawArgs = button.getAttribute(`data-${this.identifier}-args-param`);
      let isActiveArgs;

      // "textAlign" is a special case: it is only matched on its attributes
      if ('textAlign' === name) {
        isActiveArgs = [{ textAlign: rawArgs }];
      } else {
        const parsedArgs = null !== rawArgs ? JSON.parse(rawArgs) : null;
        isActiveArgs = null !== parsedArgs ? [name, parsedArgs] : [name];
      }

      button.classList.toggle('active', this.#wysiwygEditor.isActive(...isActiveArgs));
    });
  }
}
