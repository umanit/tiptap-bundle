import Image from '@tiptap/extension-image';
import imageSize from '../image-size.js';
import imageStyleClasses from '../image-style.js';

// Contents written in another editor set the size inline (`style="width:783px;height:403px"`).
const sizeAttribute = (name) => ({
  default: null,
  parseHTML: (element) => imageSize(element.getAttribute(name)) ?? imageSize(element.style.getPropertyValue(name)),
});

export default Image.extend({
  // An image is part of a paragraph (`<p>text <img> text</p>`): as a block, it would split it.
  addOptions() {
    return { ...this.parent?.(), inline: true };
  },

  addAttributes() {
    return {
      ...this.parent?.(),
      width: sizeAttribute('width'),
      height: sizeAttribute('height'),
      class: {
        default: null,
        // Contents written in another editor set the float and the margins inline
        parseHTML: (element) => {
          const classes = (element.getAttribute('class') ?? '').split(/\s+/).filter(Boolean);
          const merged = [...new Set([...classes, ...imageStyleClasses(element.style).classes])];

          return merged.length > 0 ? merged.join(' ') : null;
        },
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },
});
