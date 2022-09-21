<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="c-loading"
    :class="{
      'c-loading--overlay': overlay,
      'flex flex-items-center space-inline-xs': !overlay
    }"
    @click.prevent="">
    <div
      v-if="overlay"
      class="c-loading__inner flex flex-items-center space-inline-xs">
      <span class="c-loading__spinner" />
      <span
        v-if="!hideLabel"
        class="c-loading__text"
        v-text="label" />
    </div>
    <template v-else>
      <span class="c-loading__spinner" />
      <span
        v-if="!hideLabel"
        class="c-loading__text"
        v-text="label" />
    </template>
  </div>
</template>

<script>
import { de } from '../shared/translations'

export default {
  name: 'DpLoading',

  props: {
    hideLabel: {
      required: false,
      type: Boolean,
      default: false
    },

    overlay: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      label: de.loadingData
    }
  }
}
</script>
