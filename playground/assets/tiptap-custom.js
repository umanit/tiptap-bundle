import { Mark } from '@tiptap/core';
import { registerExtension, registerManager, registerToolState } from '@umanit/tiptap-bundle';

// A mark of the application: `<mark>`, with the `toggleHighlight` command the toolbar tool runs.
const Highlight = Mark.create({
  name: 'highlight',
  parseHTML: () => [{ tag: 'mark' }],
  renderHTML: ({ HTMLAttributes }) => ['mark', HTMLAttributes, 0],
  addCommands() {
    return {
      toggleHighlight: () => ({ commands }) => commands.toggleMark(this.name),
    };
  },
});

registerExtension(Highlight);
registerToolState('toggleHighlight', (editor) => editor.isActive('highlight'));

// A manager of the application: its `insert` method is the `callout.insert` command of a toolbar tool.
registerManager('callout', ({ editor }) => ({
  insert({ text = 'Note' } = {}) {
    editor.chain().focus().insertContent(`<blockquote><p>${text}</p></blockquote>`).run();
  },
}));
