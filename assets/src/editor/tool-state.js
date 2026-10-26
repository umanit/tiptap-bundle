import { getRegisteredToolState } from './registry.js';

/**
 * Tells when a toolbar tool is active, from the command it runs. The commands of the bundle are listed here; an
 * application adds its own with `registerToolState()`. A command which is not listed has no active state.
 *
 * @typedef {import('./registry.js').ToolStateResolver} ToolStateResolver
 */

/** @type {(name: string) => ToolStateResolver} */
const node = (name) => (editor) => editor.isActive(name);

/** @type {ToolStateResolver} */
const spanClass = (editor, className) => 'string' === typeof className && '' !== className
  && editor.hasSpanClass(className);

/** @type {Object<string, ToolStateResolver>} */
export const TOOL_STATES = {
  setParagraph: node('paragraph'),
  toggleHeading: (editor, attributes) => editor.isActive('heading', attributes),
  toggleBold: node('bold'),
  toggleItalic: node('italic'),
  toggleUnderline: node('underline'),
  toggleStrike: node('strike'),
  toggleSubscript: node('subscript'),
  toggleSuperscript: node('superscript'),
  toggleBulletList: node('bulletList'),
  toggleOrderedList: node('orderedList'),
  toggleBlockquote: node('blockquote'),
  // Only matched on its attribute
  toggleTextAlign: (editor, textAlign) => editor.isActive({ textAlign }),
  toggleListTight: (editor) => editor.isListTight(),
  'image.open': node('image'),
  // These commands set a class on the text: they are active when the selection has it
  setTextColor: spanClass,
  setTextBgColor: spanClass,
  setTextSize: spanClass,
};

/**
 * Commands which, on purpose, have no active state: an action (not a toggle), or a modal which opens.
 */
export const STATELESS_COMMANDS = [
  'undo', 'redo', 'sinkListItem', 'liftListItem',
  'clipboard.cut', 'clipboard.copy', 'clipboard.paste',
  'link.open', 'link.unset', 'anchor.open', 'template.open', 'table.openAddModal',
  'setBlockClass', 'unsetTextColor', 'unsetTextBgColor', 'unsetTextSize',
];

/**
 * @param {string} command
 *
 * @returns {ToolStateResolver|undefined}
 */
export function getToolState(command) {
  return getRegisteredToolState(command) ?? TOOL_STATES[command];
}

/**
 * Reads the arguments of a tool back from its attribute: Stimulus renders a string as it is and the rest as JSON.
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
