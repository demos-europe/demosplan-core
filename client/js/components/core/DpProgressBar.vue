<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-progress-bar">
    <template v-if="indeterminate">
      <div class="u-mb-0_25 weight--bold">
        {{ label }}
      </div>

      <div class="c-progress-bar__line is-animated" />
    </template>

    <template v-else>
      <div class="u-mb-0_25 weight--bold">
        {{ label }}
      </div>

      <div class="bg-color--grey-light-2">
        <div
          class="u-mb-0_25 c-progress-bar__line"
          :style="style" />
      </div>
      <span>
        {{ percentage }}%
      </span>
    </template>
  </div>
</template>

<script>
export default {
  name: 'DpProgressBar',

  props: {
    indeterminate: {
      type: Boolean,
      required: false,
      default: false
    },

    label: {
      type: String,
      required: false,
      default: ''
    },

    percentage: {
      type: Number,
      required: false,
      default: 0
    }
  },

  computed: {
    style () {
      return 'width: ' + this.percentage + '%;'
    }
  }
}
</script>
