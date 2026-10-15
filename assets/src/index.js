import { registerFileManagerAdapter } from './file-manager/registry.js';
import defaultAdapter, { DEFAULT_ADAPTER_NAME } from './file-manager/default-adapter.js';

registerFileManagerAdapter(DEFAULT_ADAPTER_NAME, defaultAdapter);

export { registerFileManagerAdapter } from './file-manager/registry.js';
export { default as TipTapEditor } from './editor/tiptap-editor.js';
