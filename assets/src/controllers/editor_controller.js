import { Controller } from '@hotwired/stimulus';
import { MODAL_PROPERTY, SET_CONTENT_EVENT } from '../constants.js';
import TipTapEditor from '../editor/tiptap-editor.js';
import HtmlCleaner from '../editor/html-cleaner.js';
import HtmlFormatter from '../editor/html-formatter.js';
import { analyzeContent } from '../editor/migration-diff.js';
import { renderDiffLists, renderDiffPreviews } from '../editor/diff-viewer.js';
import { DEFAULT_ADAPTER_NAME } from '../file-manager/default-adapter.js';
import { createFileManagerAdapter } from '../file-manager/registry.js';
import { isToolActive, parseToolArgs } from '../editor/tool-state.js';
import '../index.js';

const MIN_HEIGHT = 120;
const RESIZE_STEP = 40;

export default class extends Controller {
  static targets = [
    'editor', 'input', 'toolbar', 'path', 'resizeHandle', 'contextMenu', 'modals',
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
          this.#updatePath();
        },
        onSelectionUpdate: () => {
          this.#updateToolbarState();
          this.#updatePath();
        },
      }, {
        placeholder: this.translationsValue.placeholder || null,
      }, fileManagerAdapter, {
        open: (el) => this.openModal(el),
        close: (el) => this.closeModal(el),
      });

      this.#wysiwygEditor.onSourceModeChange((isSourceMode) => this.#updateSourceModeUI(isSourceMode));

      this.#setupEventListeners();
      this.#updatePath();
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

  /**
   * Resizes the editing area (and the source view) by dragging the handle of the status bar.
   *
   * @param {PointerEvent} event
   */
  startResize(event) {
    event.preventDefault();

    const startY = event.clientY;
    const startHeight = this.#areaHeight();
    const { signal } = this.#abortController;
    const stop = new AbortController();
    const end = () => stop.abort();

    this.resizeHandleTarget.setPointerCapture?.(event.pointerId);
    this.resizeHandleTarget.addEventListener(
      'pointermove',
      (move) => this.#setAreaHeight(startHeight + move.clientY - startY),
      { signal: AbortSignal.any([signal, stop.signal]) },
    );
    this.resizeHandleTarget.addEventListener('pointerup', end, { signal: stop.signal });
    this.resizeHandleTarget.addEventListener('pointercancel', end, { signal: stop.signal });
  }

  /**
   * @param {KeyboardEvent} event
   */
  resizeWithKeyboard(event) {
    const direction = { ArrowUp: -1, ArrowDown: 1 }[event.key];

    if (undefined === direction) {
      return;
    }

    event.preventDefault();
    this.#setAreaHeight(this.#areaHeight() + direction * RESIZE_STEP);
  }

  openDiff() {
    if (!this.#migration || !this.hasDiffBeforeTarget) {
      return;
    }

    const { before, result } = this.#migration;

    renderDiffLists(this.diffSignalsTarget, this.diffLinesTarget, result);
    this.openModal(this.diffBeforeTarget);
    renderDiffPreviews(this.diffBeforeTarget, this.diffAfterTarget, before, result.after);
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

  /**
   * @returns {number} The current height of the editing area, in pixels
   */
  #areaHeight() {
    const area = this.#wysiwygEditor.isSourceMode() ? this.inputTarget : this.editorTarget.parentElement;

    return area.getBoundingClientRect().height;
  }

  /**
   * The height is a custom property of the root: the editing area and the source view both read it.
   *
   * @param {number} height
   */
  #setAreaHeight(height) {
    this.element.style.setProperty('--umanit-tiptap-height', `${Math.max(MIN_HEIGHT, Math.round(height))}px`);
  }

  #updatePath() {
    if (!this.hasPathTarget || !this.#wysiwygEditor) {
      return;
    }

    this.pathTarget.replaceChildren(...this.#wysiwygEditor.getPath().map((tag) => {
      const item = document.createElement('li');
      item.textContent = tag;

      return item;
    }));
  }

  #updateSourceModeUI(isSourceMode) {
    this.editorTarget.classList.toggle('ut-hidden', isSourceMode);
    this.inputTarget.classList.toggle('ut-hidden', !isSourceMode);
    this.toolbarTarget.classList.toggle('umanit-tiptap__toolbar--disabled', isSourceMode);
    this.editorTarget.parentElement.classList.toggle('ut-hidden', isSourceMode);
  }

  #updateToolbarState() {
    // The tools, and the entries of the dropdowns (a color, a size, a style) which tell when they are active
    const selector = `[data-wysiwyg-tool], [data-${this.identifier}-active-param]`;

    this.toolbarTarget.querySelectorAll(selector).forEach((button) => {
      const param = (name) => parseToolArgs(button.getAttribute(`data-${this.identifier}-${name}-param`));
      const active = isToolActive(this.#wysiwygEditor, param('command'), param('args'), param('active'));

      if (null !== active) {
        button.classList.toggle('active', active);
      }
    });
  }
}
