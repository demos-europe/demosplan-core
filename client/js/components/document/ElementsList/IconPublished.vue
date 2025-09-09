<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <i
      class="fa cursor-help"
      :class="{
        'fa-check color-message-success-fill': published,
        'fa-times-circle color-message-severe-fill': published === false
      }"
      aria-hidden="true"
      v-tooltip="text"
    />
    <span
      class="sr-only"
      v-text="text" />
  </div>
</template>

<script>
export default {
  name: 'IconPublished',

  props: {
    published: {
      type: Boolean,
      required: true
    }
  },

  computed: {
    text () {
      return Translator.trans(this.published ? 'published' : 'unpublished')
    }
  }
}
</script>
