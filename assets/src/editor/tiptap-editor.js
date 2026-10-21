import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableRow } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import OfficePaste from '@intevation/tiptap-extension-office-paste';
import CustomTextColor from './extensions/custom-text-color.js';
import CustomTextBgColor from './extensions/custom-text-bg-color.js';
import CustomTextAlign from './extensions/custom-text-align.js';
import TableManager from './managers/table-manager.js';
import LinkManager from './managers/link-manager.js';
import ImageManager from './managers/image-manager.js';
import TemplateManager from './managers/template-manager.js';
import ClipboardManager from './managers/clipboard-manager.js';
import AnchorManager from './managers/anchor-manager.js';
import CustomAnchor from './extensions/custom-anchor.js';
import CustomTextSize from './extensions/custom-text-size.js';
import CustomBlockClasses from './extensions/custom-block-classes.js';
import { CustomTable, CustomTableCell, CustomTableHeader } from './extensions/custom-table.js';
import CustomImage from './extensions/custom-image.js';
import CustomListItem from './extensions/custom-list-item.js';
import { closestList, hasTightClass } from './tight-list.js';

/**
 * @typedef {Object} ManagersElements
 * @property {HTMLFormElement} [tableForm] - The form element for managing tables.
 * @property {HTMLFormElement} [cellForm] - The form element for managing table cells.
 * @property {HTMLElement} [contextMenu] - The context menu element associated with table interactions.
 * @property {HTMLFormElement} [linkForm] - The form element for managing links.
 * @property {HTMLElement} [linkUrlGroup] - The group element for managing link URLs.
 * @property {HTMLElement} [linkAnchorGroup] - The group element for managing link anchors.
 * @property {HTMLFormElement} [imageForm] - The form element for managing images.
 * @property {HTMLFormElement} [templateForm] - The form element for managing templates.
 * @property {HTMLFormElement} [anchorForm] - The form element for managing anchors.
 *
 * @typedef {((onSelect: (url: string) => void) => void)|null} FileManagerAdapter
 *
 * @typedef {Object} Translations
 * @property {string} [placeholder] - The placeholder text for the editor.
 *
 * @typedef {Object} LegacyStyles
 * @property {Object<string, string>} [color] - Inline `color` value (`#ff0000`, `rgb(…)`) => `ut-color-*` class.
 * @property {Object<string, string>} [backgroundColor] - Inline `background-color` value => `ut-bg-*` class.
 * @property {Object<string, string>} [fontSize] - Inline `font-size` value (`14px`, `10.5pt`) => `ut-fs-*` class.
 */

export default class TipTapEditor {
  #editor;
  #managers = {};
  #isSourceMode = false;
  #sourceModeCallback = null;

  /**
   * @param {ManagersElements} managersElements
   * @param {Object} editorOptions TipTap options, plus `legacyStyles` (LegacyStyles) to import contents written by
   *   another editor: their inline color, background and font size are mapped onto the given palette classes.
   *   `trailingNode: false` stops the editor from adding an empty paragraph after a last list, table or image.
   * @param {Translations} translations
   * @param {FileManagerAdapter} fileManagerAdapter
   * @param {{ open?: (element: HTMLElement) => void, close?: (element: HTMLElement) => void }} modalCallbacks
   */
  constructor(managersElements, editorOptions = {}, translations = {}, fileManagerAdapter = null, modalCallbacks = {}) {
    const { legacyStyles = null, trailingNode = true, ...tiptapOptions } = editorOptions;

    this.#editor = new Editor({
      extensions: this.#getExtensions(translations, legacyStyles, trailingNode),
      ...tiptapOptions,
      editorProps: {
        ...tiptapOptions.editorProps,
        attributes: {
          ...tiptapOptions.editorProps?.attributes,
          class: ['umanit-tiptap__content', tiptapOptions.editorProps?.attributes?.class].filter(Boolean).join(' '),
        },
      },
    });

    this.#initManagers(managersElements, fileManagerAdapter, modalCallbacks);
  }

  /**
   * @returns {import('@tiptap/core').Editor}
   */
  getEditor() {
    return this.#editor;
  }

  /**
   * @returns {string}
   */
  getHTML() {
    return this.#editor.getHTML();
  }

  /**
   * @param {string} html
   */
  setContent(html) {
    this.#editor.commands.setContent(html);
  }

  /**
   * @param {string} command
   * @param {any} args
   */
  runCommand(command, args = null) {
    if (command.includes('.')) {
      this.#runManagerCommand(command, args);
    } else {
      this.#runEditorCommand(command, args);
    }
  }

  destroy() {
    this.#editor.destroy();
  }

  /**
   * @returns {boolean} Whether the closest list around the cursor packs its items
   */
  isListTight() {
    const list = closestList(this.#editor.state.selection.$from);

    return list !== null && hasTightClass(list.node);
  }

  /**
   * @param {...any} args
   * @returns {boolean}
   */
  isActive(...args) {
    return this.#editor.isActive(...args);
  }

  /**
   * @returns {HTMLElement|null}
   */
  getContextMenuElement() {
    return this.#managers.table?.getContextMenuElement?.() || null;
  }

  /**
   * @param {MouseEvent} event
   */
  handleContextMenu(event) {
    this.#managers.table?.handleContextMenu(event);
  }

  hideContextMenu() {
    this.#managers.table?.hideContextMenu();
  }

  /**
   * @returns {boolean}
   */
  isSourceMode() {
    return this.#isSourceMode;
  }

  /**
   * @param {boolean} enabled
   */
  setSourceMode(enabled) {
    this.#isSourceMode = enabled;
    this.#sourceModeCallback?.(this.#isSourceMode);
  }

  /**
   * @param {Function} callback
   */
  onSourceModeChange(callback) {
    this.#sourceModeCallback = callback;
  }

  openFileManager() {
    this.#managers.image?.openFileManager();
  }

  /**
   * @param {Translations} translations
   * @param {LegacyStyles|null} legacyStyles
   * @param {boolean} trailingNode
   * @returns {import('@tiptap/core').Extension[]}
   */
  #getExtensions(translations = {}, legacyStyles = null, trailingNode = true) {
    return [
      StarterKit.configure({
        link: { openOnClick: false },
        listItem: false,
        ...(trailingNode ? {} : { trailingNode: false }),
      }),
      CustomBlockClasses,
      CustomListItem,
      CustomImage,
      Subscript,
      Superscript,
      CustomTable.configure({ resizable: false }),
      CustomTableCell,
      CustomTableHeader,
      CustomTextAlign.configure({ types: ['heading', 'paragraph'] }),
      TableRow,
      CustomTextColor.configure({ legacyStyles }),
      CustomTextBgColor.configure({ legacyStyles }),
      Placeholder.configure({
        placeholder: translations.placeholder || 'Write something …',
      }),
      OfficePaste,
      CustomAnchor,
      CustomTextSize.configure({ legacyStyles }),
    ];
  }

  /**
   * @param {ManagersElements} elements
   * @param {FileManagerAdapter} fileManagerAdapter
   * @param {{ open?: Function, close?: Function }} modalCallbacks
   */
  #initManagers(elements, fileManagerAdapter, modalCallbacks) {
    this.#managers = {
      table: new TableManager(this.#editor, {
        tableForm: elements.tableForm || null,
        cellForm: elements.cellForm || null,
        contextMenu: elements.contextMenu || null,
      }),
      link: new LinkManager(this.#editor, {
        form: elements.linkForm || null,
        urlGroup: elements.linkUrlGroup || null,
        anchorGroup: elements.linkAnchorGroup || null,
      }),
      image: new ImageManager(this.#editor, {
        form: elements.imageForm || null,
        fileManagerAdapter: fileManagerAdapter || null,
      }),
      template: new TemplateManager(this.#editor, elements.templateForm),
      clipboard: new ClipboardManager(this.#editor),
      anchor: new AnchorManager(this.#editor, elements.anchorForm || null),
    };

    if (modalCallbacks.open) {
      Object.values(this.#managers).forEach((m) => {
        m._openModal = modalCallbacks.open;
      });
    }
    if (modalCallbacks.close) {
      Object.values(this.#managers).forEach((m) => {
        m._closeModal = modalCallbacks.close;
      });
    }
  }

  #runManagerCommand(command, args = null) {
    const [managerName, methodName] = command.split('.');
    const manager = this.#managers[managerName];

    if (manager && typeof manager[methodName] === 'function') {
      manager[methodName](args);
    } else {
      throw new Error(`Unknown manager "${command}"`);
    }
  }

  #runEditorCommand(command, args = null) {
    const chain = this.#editor.chain().focus();

    if (typeof chain[command] !== 'function') {
      throw new Error(`Unknown TipTap command "${command}"`);
    }

    if (args !== null) {
      chain[command](args).run();
    } else {
      chain[command]().run();
    }
  }
}
