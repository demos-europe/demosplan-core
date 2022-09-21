<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button
    class="btn--icon"
    v-tooltip="{
      content: translatedText,
      delay: { show: 1000, hide: 200 }
    }"
    @click="$emit('click')">
    <i
      :class="`fa ${icon}`"
      aria-hidden="true" />
    <span class="hide-visually">{{ translatedText }}</span>
  </button>
</template>

<script>
export default {
  name: 'DpButtonIcon',

  props: {
    icon: {
      type: String,
      required: true
    },

    text: {
      type: String,
      required: true
    }
  },

  computed: {
    translatedText () {
      return Translator.trans(this.text)
    }
  }
}
</script>
