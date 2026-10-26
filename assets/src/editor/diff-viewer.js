const MARKS = {
  removed: '-', added: '+', same: ' ', gap: ' ',
};

/**
 * @param {string} html
 *
 * @returns {string} A page which shows the content with the stylesheets of the current page
 */
function previewDocument(html) {
  const stylesheets = [...document.querySelectorAll('link[rel="stylesheet"]')]
    .map((link) => link.cloneNode().outerHTML)
    .join('');

  return `<!doctype html><html><head>${stylesheets}`
    + '<style>body{margin:0}.umanit-tiptap__content{min-height:0;padding:1rem}</style></head>'
    + `<body class="umanit-tiptap__content">${html}</body></html>`;
}

/**
 * Lists the lost signals and the lines of the difference. The contents are put in the page as text only: they are
 * not to be trusted.
 *
 * @param {HTMLElement} signalsElement
 * @param {HTMLElement} linesElement
 * @param {ReturnType<typeof import('./migration-diff.js').analyzeContent>} result
 */
export function renderDiffLists(signalsElement, linesElement, result) {
  signalsElement.replaceChildren(...result.lost.map(({ signal, before, after }) => {
    const item = document.createElement('li');
    item.textContent = `${signal} (${before} → ${after})`;

    return item;
  }));

  linesElement.replaceChildren(...result.lines.map(({ type, line }) => {
    const item = document.createElement('code');
    item.className = `umanit-tiptap__diff-line umanit-tiptap__diff-line--${type}`;
    item.textContent = `${MARKS[type]} ${line}`;

    return item;
  }));
}

/**
 * Shows the contents in two frames. A fresh iframe is made each time, to call once the dialog is open: one which
 * loaded its `srcdoc` while the closed dialog hid it stays blank when opened again, whatever is assigned to it
 * afterwards.
 *
 * @param {HTMLIFrameElement} beforeFrame
 * @param {HTMLIFrameElement} afterFrame
 * @param {string} before
 * @param {string} after
 */
export function renderDiffPreviews(beforeFrame, afterFrame, before, after) {
  [[beforeFrame, before], [afterFrame, after]].forEach(([iframe, html]) => {
    const fresh = iframe.cloneNode(false);
    fresh.removeAttribute('srcdoc');
    iframe.replaceWith(fresh);
    fresh.srcdoc = previewDocument(html);
  });
}
