import { registerFileManagerAdapter } from './file-manager/registry.js';
import defaultAdapter, { DEFAULT_ADAPTER_NAME } from './file-manager/default-adapter.js';

registerFileManagerAdapter(DEFAULT_ADAPTER_NAME, defaultAdapter);

export { registerFileManagerAdapter } from './file-manager/registry.js';
export { default as TipTapEditor } from './editor/tiptap-editor.js';
export { registerExtension, registerManager, registerToolState } from './editor/registry.js';
export { getTipTapEditor } from './editor/instance.js';
export { EDITOR_PROPERTY, READY_EVENT, SET_CONTENT_EVENT } from './constants.js';
