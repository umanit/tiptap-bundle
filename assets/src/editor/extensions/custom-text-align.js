import TextAlign from '@tiptap/extension-text-align';

const CLASS_MAP = {
  left: 'ut-text-left',
  center: 'ut-text-center',
  right: 'ut-text-right',
  justify: 'ut-text-justify',
};

export default TextAlign.extend({
  addGlobalAttributes() {
    return (this.parent?.() ?? []).map((config) => ({
      ...config,
      attributes: {
        ...config.attributes,
        textAlign: {
          ...config.attributes?.textAlign,
          parseHTML: (element) => {
            const classes = (element.className || '').split(' ');
            for (const [align, cls] of Object.entries(CLASS_MAP)) {
              if (classes.includes(cls)) return align;
            }
            return element.style?.textAlign || null;
          },
          renderHTML: (attributes) => {
            if (!attributes.textAlign) return {};
            const cls = CLASS_MAP[attributes.textAlign];
            return cls ? { class: cls } : {};
          },
        },
      },
    }));
  },
});
