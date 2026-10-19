import Image from '@tiptap/extension-image';
import imageSize from '../image-size.js';

// Contents written in another editor set the size inline (`style="width:783px;height:403px"`).
const sizeAttribute = (name) => ({
  default: null,
  parseHTML: (element) => imageSize(element.getAttribute(name)) ?? imageSize(element.style.getPropertyValue(name)),
});

export default Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      width: sizeAttribute('width'),
      height: sizeAttribute('height'),
      class: {
        default: null,
        parseHTML: (element) => element.getAttribute('class'),
        renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
      },
    };
  },
});
