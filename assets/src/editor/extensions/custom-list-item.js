import { ListItem } from '@tiptap/extension-list';

// A list item accepts any block first (heading, paragraph, …), like CKEditor,
// instead of the default "paragraph block*" which forces a leading paragraph.
export default ListItem.extend({
  content: 'block+',
});
