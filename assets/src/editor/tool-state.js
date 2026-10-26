import { getRegisteredToolState } from './registry.js';

/**
 * Tells when a toolbar tool is active, at the cursor. The tool says how, in the `active` of its definition
 * (`Tool::$active` on the PHP side), rendered as a JSON attribute of its button:
 *
 * - `{ name: 'bold' }`: the node or the mark is active;
 * - `{ name: 'heading', attributes: { level: 2 } }`: ... with these attributes;
 * - `{ attributes: { textAlign: 'left' } }`: an attribute which is set, whatever the node;
 * - `{ spanClass: 'ut-color-red' }`: the selection is in a span which has the class (a color, a size);
 * - `{ blockClass: 'ut-banner-info' }`: the text block at the cursor has the class (a block style);
 * - `{ state: 'listTight' }`: a state of the editor which is not a node nor a mark (see `STATES`).
 *
 * An application can also register a resolver for a command (`registerToolState()`): it takes precedence.
 *
 * @typedef {{
 *   name?: string, attributes?: Object<string, any>, spanClass?: string, blockClass?: string, state?: string
 * }} ActiveDescriptor
 * @typedef {import('./registry.js').ToolStateResolver} ToolStateResolver
 */

/** @type {Object<string, (editor: import('./tiptap-editor.js').default) => boolean>} */
export const STATES = {
  listTight: (editor) => editor.isListTight(),
};

const DESCRIPTOR_KEYS = ['name', 'attributes', 'spanClass', 'blockClass', 'state'];

/**
 * @param {import('./tiptap-editor.js').default} editor
 * @param {ActiveDescriptor} descriptor
 *
 * @returns {boolean}
 */
export function resolveActive(editor, descriptor) {
  const unknown = Object.keys(descriptor).filter((key) => !DESCRIPTOR_KEYS.includes(key));

  if (unknown.length > 0) {
    throw new Error(`Unknown key(s) in the active state of a tool: ${unknown.join(', ')}.`);
  }

  const {
    name, attributes, spanClass, blockClass, state,
  } = descriptor;

  if (undefined !== state) {
    if (!(state in STATES)) {
      throw new Error(`Unknown editor state "${state}". Known states: ${Object.keys(STATES).join(', ')}.`);
    }

    return STATES[state](editor);
  }

  if (undefined !== spanClass) {
    return editor.hasSpanClass(spanClass);
  }

  if (undefined !== blockClass) {
    return editor.hasBlockClass(blockClass);
  }

  if (undefined === name && undefined === attributes) {
    throw new Error('The active state of a tool needs a "name", "attributes", a class or a "state".');
  }

  return undefined === name ? editor.isActive(attributes) : editor.isActive(name, attributes);
}

/**
 * @param {import('./tiptap-editor.js').default} editor
 * @param {string|null} command The command the tool runs
 * @param {any} args The arguments of the tool
 * @param {ActiveDescriptor|null} descriptor
 *
 * @returns {boolean|null} Whether the tool is active, or null when it has no active state
 */
export function isToolActive(editor, command, args, descriptor) {
  const registered = command ? getRegisteredToolState(command) : undefined;

  if (registered) {
    return registered(editor, args);
  }

  return null === descriptor ? null : resolveActive(editor, descriptor);
}

/**
 * Reads the value of an attribute of a tool back: Stimulus renders a string as it is and the rest as JSON.
 *
 * @param {string|null} raw
 *
 * @returns {any}
 */
export function parseToolArgs(raw) {
  if (null === raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return raw;
  }
}
