<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  this is the button that copies text fro one textarea/editor and passes it to tiptap editor.
  Props:
  - copyContent - the content that we want to copy
  - buttonlabel - the label on the button

  This component takes care of the fact that we should pass to tiptap content without dp-obscure components (see: addToTarget function)
</documentation>

<template>
  <a
    @click.stop.prevent="$emit('set-text', copyContent)"
    href="">
    <i
      aria-hidden="true"
      class="fa fa-files-o" />
    {{ Translator.trans(buttonLabel) }}
  </a>
</template>

<script>
export default {
  name: 'DpCopyPasteButton',

  props: {
    copyContent: {
      required: true,
      type: String
    },

    buttonLabel: {
      required: false,
      type: String,
      default: 'copy'
    }
  }
}
</script>
