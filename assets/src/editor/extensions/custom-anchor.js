import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

export default Extension.create({
  name: 'anchor',

  addGlobalAttributes() {
    return [{
      types: ['blockquote', 'codeBlock', 'heading', 'paragraph'],
      attributes: {
        id: {
          default: null,
          keepOnSplit: false,
          parseHTML: (element) => {
            if (element.getAttribute('id')) {
              return element.getAttribute('id');
            }

            // BC for CKEditor anchors which are on "a" tag
            // `<p><a id="foo"></a>Foo</p>` will become `<p id="foo">Foo</p>`
            return element.querySelector('a[id]:not([href])')?.getAttribute('id') ?? null;
          },
          renderHTML: ({ id }) => (id ? { id } : {}),
        },
      },
    }];
  },

  addProseMirrorPlugins() {
    return [
      new Plugin({
        key: new PluginKey('anchor-decoration'),
        props: {
          decorations(state) {
            const decorations = [];
            state.doc.descendants((node, pos) => {
              if (node.attrs.id) {
                decorations.push(Decoration.node(pos, pos + node.nodeSize, { class: 'umanit-tiptap-anchor' }));
              }
            });

            return DecorationSet.create(state.doc, decorations);
          },
        },
      }),
    ];
  },
});
