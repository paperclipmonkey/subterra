import { markRaw } from 'vue'
import { VuetifyTiptap, createVuetifyProTipTap } from 'vuetify-pro-tiptap'
import { BaseKit, Bold, Italic, Strike, Heading, BulletList, OrderedList, Link, Blockquote, HorizontalRule, Fullscreen } from 'vuetify-pro-tiptap'
import { Markdown } from 'tiptap-markdown';
import 'vuetify-pro-tiptap/style.css'

export const vuetifyProTipTap = createVuetifyProTipTap({
  lang: 'english',
  components: {
    VuetifyTiptap
  },
  markdownTheme: 'default',
  extensions: [
    BaseKit.configure({
      placeholder: {
        placeholder: 'Describe your adventure...',
      },
      characterCount: false,
    }),
    Bold,
    Italic,
    Strike,
    Heading,
    BulletList,
    OrderedList,
    Link,
    Blockquote,
    HorizontalRule,
    Fullscreen,
    Markdown
  ]
})