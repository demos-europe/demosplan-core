<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="border u-mb u-p-0_25">
    <editor-menu-bubble
      :editor="editor"
      class="editor-menububble__wrapper"
      :keep-in-bounds="true"
      v-slot:default="{ commands, isActive, menu }">
      <div
        :class="{ 'is-active': menu.isActive }"
        :style="`left: ${menu.left}px; bottom: ${menu.bottom}px;`">
        <a
          v-if="isActive.anonymize()"
          class="editor-menububble__button is-active"
          @click="commands.unanonymize">
          {{ Translator.trans('statement.anonymize.unmark') }}
        </a>
        <a
          v-else
          class="editor-menububble__button"
          @click="commands.anonymize">
          {{ Translator.trans('statement.anonymize.mark') }}
        </a>
      </div>
    </editor-menu-bubble>
    <editor-content
      autocomplete="off"
      autocorrect="off"
      autocapitalize="off"
      spellcheck="false"
      ref="editorContent"
      class="editor-content"
      :editor="editor" />
  </div>
</template>

<script>
import {
  Bold,
  BulletList,
  HardBreak,
  History,
  Italic,
  ListItem,
  OrderedList,
  Underline
} from 'tiptap-extensions'
import { Editor, EditorContent, EditorMenuBubble } from 'tiptap'
import EditorAnonymize from './DpEditor/libs/editorAnonymize'
import EditorObscure from './DpEditor/libs/editorObscure'
import EditorUnAnonymize from './DpEditor/libs/editorUnAnonymize'
import PreventDrop from './DpEditor/libs/preventDrop'
import PreventKeyboardInput from './DpEditor/libs/preventKeyboardInput'

export default {
  name: 'DpAnonymizeText',

  components: {
    EditorContent,
    EditorMenuBubble
  },

  props: {
    value: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      editor: null
    }
  },

  methods: {
    setValue () {
      let currentValue = this.editor.getHTML()

      // 1. look if there are anonymized segements, which are tagged to un-anonymize
      const unanonymize = /<span[^>]*?title="(.*?)"([^>]*?)class="anonymize-me"([^>]*?)>([^<]*?)<span class="unanonymized">([^<]*?)<\/span>([^<]*?)<\/span>/gm
      currentValue = currentValue.replace(unanonymize, (match, p1) => p1.replaceAll('&quot;', '"'))

      // 2. remove unanonymize tags that are left over because the selection was wider than the anonymized element
      const unanonymizeCleaner = /<span class="unanonymized">(.*?)<\/span>/gm
      currentValue = currentValue.replace(unanonymizeCleaner, '$1')

      // 3. anonymize text - leave the text in the title, that it can be restored again.
      const anonymize = /<span class="anonymize-me">(.*?)<\/span>/gm
      currentValue = currentValue.replace(anonymize, (match, p1) => ('<span title="' + p1.replaceAll('"', '&quot;') + '" class="anonymize-me">***</span>'))

      // Update text
      this.editor.setContent(currentValue)
      this.$emit('change', currentValue)
    }
  },

  mounted () {
    this.editor = new Editor({
      content: this.value,
      editable: true,
      disableInputRules: true,
      disablePasteRules: true,
      extensions: [
        new EditorAnonymize(),
        new EditorUnAnonymize(),
        new EditorObscure(),
        new PreventKeyboardInput(),
        new PreventDrop(),
        new Bold(),
        new Italic(),
        new BulletList(),
        new OrderedList(),
        new ListItem(),
        new Underline(),
        new History(),
        new HardBreak()
      ],
      onUpdate: () => {
        this.setValue()
      },
      editorProps: {
        handleTextInput: () => true // Disable text input
      }
    })
  }
}
</script>
