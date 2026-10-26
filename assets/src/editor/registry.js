/**
 * @typedef {import('@tiptap/core').AnyExtension} TipTapExtension
 *
 * @typedef {Object} ManagerContext
 * @property {import('@tiptap/core').Editor} editor
 * @property {Object<string, HTMLElement|null>} elements - The elements of the editor (forms, groups, context menu).
 * @property {((url: string) => void)|null} fileManagerAdapter
 * @property {{ open?: (element: HTMLElement) => void, close?: (element: HTMLElement) => void }} modal
 *
 * @typedef {(context: ManagerContext) => Object} ManagerFactory
 *
 * @typedef {(editor: import('./tiptap-editor.js').default, args: any) => boolean} ToolStateResolver
 */

/** @type {TipTapExtension[]} */
const extensions = [];

/** @type {Map<string, ManagerFactory>} */
const managers = new Map();

/** @type {Map<string, ToolStateResolver>} */
const toolStates = new Map();

/**
 * Adds a TipTap extension (extension, node or mark) to every editor created afterwards.
 *
 * Register it before the editors are connected (at the top of your entrypoint). The extension is added after the ones
 * of the bundle; its commands can then be bound to a toolbar tool.
 *
 * @param {TipTapExtension} extension
 */
export function registerExtension(extension) {
  extensions.push(extension);
}

/**
 * Registers a manager: an object whose public methods are the commands `<name>.<method>` of the toolbar tools.
 *
 * The factory is called once per editor. The manager may have a `destroy()` method, called with the editor.
 * A name already used by a manager of the bundle (`table`, `link`, `image`, `template`, `clipboard`, `anchor`)
 * replaces it.
 *
 * @param {string} name
 * @param {ManagerFactory} factory
 */
export function registerManager(name, factory) {
  if (!name || name.includes('.')) {
    throw new Error(`Invalid manager name "${name}": it must not be empty nor contain a ".".`);
  }

  managers.set(name, factory);
}

/**
 * Tells when a toolbar tool is active, from the command it runs.
 *
 * The resolver receives the editor and the arguments of the tool (as rendered by Twig) and returns a boolean.
 * The commands of the bundle are declared in `tool-state.js`; one registered here takes precedence over them.
 * A command without a resolver has no active state.
 *
 * @param {string} command
 * @param {ToolStateResolver} resolver
 */
export function registerToolState(command, resolver) {
  toolStates.set(command, resolver);
}

/**
 * @returns {TipTapExtension[]}
 */
export function getRegisteredExtensions() {
  return [...extensions];
}

/**
 * @returns {Map<string, ManagerFactory>}
 */
export function getRegisteredManagers() {
  return new Map(managers);
}

/**
 * @param {string} command
 *
 * @returns {ToolStateResolver|undefined}
 */
export function getRegisteredToolState(command) {
  return toolStates.get(command);
}

/**
 * Forgets everything which was registered: for the tests only.
 */
export function resetRegistry() {
  extensions.length = 0;
  managers.clear();
  toolStates.clear();
}
