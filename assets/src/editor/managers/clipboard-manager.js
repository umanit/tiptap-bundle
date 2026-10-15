import { DOMSerializer } from '@tiptap/pm/model';
import AbstractManager from './abstract-manager.js';

export default class ClipboardManager extends AbstractManager {
  cut() {
    if (this._editor.state.selection.empty) {
      return;
    }

    this.#writeSelection().then(() => this._editor.chain().focus().deleteSelection().run());
  }

  copy() {
    if (this._editor.state.selection.empty) {
      return;
    }

    this.#writeSelection();
  }

  async paste() {
    try {
      try {
        const items = await navigator.clipboard.read();

        const htmlItem = items.find((item) => item.types.includes('text/html'));
        if (htmlItem) {
          this.#insertContent(await (await htmlItem.getType('text/html')).text());

          return;
        }

        const textItem = items.find((item) => item.types.includes('text/plain'));
        if (textItem) {
          this.#insertContent(await (await textItem.getType('text/plain')).text());
        }
      } catch {
        this.#insertContent(await navigator.clipboard.readText());
      }
    } catch {
      this._editor.commands.focus();
    }
  }

  #writeSelection() {
    const { state } = this._editor;
    const { from, to } = state.selection;
    const fragment = state.doc.slice(from, to).content;
    const serializer = DOMSerializer.fromSchema(state.schema);
    const div = document.createElement('div');
    div.appendChild(serializer.serializeFragment(fragment));

    return navigator.clipboard.write([
      new ClipboardItem({
        'text/plain': new Blob([div.textContent], { type: 'text/plain' }),
        'text/html': new Blob([div.innerHTML], { type: 'text/html' }),
      }),
    ]);
  }

  #insertContent(content) {
    this._editor.chain().focus().insertContent(content).run();
  }
}
