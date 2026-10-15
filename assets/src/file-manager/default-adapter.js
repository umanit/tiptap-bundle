export const DEFAULT_ADAPTER_NAME = 'default';

export const MESSAGE_TYPE = 'umanit-tiptap:file-selected';

/**
 * Default adapter: opens the file manager in a popup and waits for a `postMessage` sent by the popup
 * (`window.opener.postMessage({ type: 'umanit-tiptap:file-selected', url }, origin)`).
 *
 * @param {string} fileManagerUrl
 *
 * @returns {import('./registry.js').FileManagerAdapter}
 */
export default function defaultAdapter(fileManagerUrl) {
  return (onSelect) => {
    const url = new URL(fileManagerUrl, window.location.origin);

    const onMessage = (event) => {
      if (event.origin !== url.origin || event.data?.type !== MESSAGE_TYPE) {
        return;
      }

      window.removeEventListener('message', onMessage);
      onSelect(event.data.url);
    };

    window.addEventListener('message', onMessage);

    const width = 1200;
    const height = 800;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;

    window.open(
      url.toString(),
      'umanit-tiptap-file-manager',
      `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`,
    );
  };
}
