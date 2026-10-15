/**
 * @typedef {(onSelect: (url: string) => void) => void} FileManagerAdapter
 * @typedef {(fileManagerUrl: string) => FileManagerAdapter} FileManagerAdapterFactory
 */

/** @type {Map<string, FileManagerAdapterFactory>} */
const factories = new Map();

/**
 * Registers a file manager adapter factory under the given name.
 *
 * The factory receives the `fileManagerUrl` given to the editor and returns a function which opens the file manager
 * and calls `onSelect` with the URL of the picked file.
 *
 * @param {string} name
 * @param {FileManagerAdapterFactory} factory
 */
export function registerFileManagerAdapter(name, factory) {
  factories.set(name, factory);
}

/**
 * @param {string} name
 * @param {string} fileManagerUrl
 *
 * @returns {FileManagerAdapter}
 */
export function createFileManagerAdapter(name, fileManagerUrl) {
  const factory = factories.get(name);

  if (!factory) {
    throw new Error(
      `Unknown file manager adapter "${name}". Registered adapters: ${[...factories.keys()].join(', ') || 'none'}.`,
    );
  }

  return factory(fileManagerUrl);
}
