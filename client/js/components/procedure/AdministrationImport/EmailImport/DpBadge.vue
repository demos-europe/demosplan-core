<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <span
    :class="`border-radius-small ${colorClasses} ${sizeClasses}`"
    v-text="text" />
</template>

<script>
export default {
  name: 'DpBadge',

  props: {
    color: {
      type: String,
      required: false,
      default: 'default',
      validator: (prop) => ['confirm', 'default', 'error', 'info', 'warning'].includes(prop)
    },

    size: {
      type: String,
      required: false,
      default: 'medium',
      validator: (prop) => ['small', 'medium', 'large'].includes(prop)
    },

    text: {
      required: true,
      type: String
    }
  },

  computed: {
    colorClasses () {
      const cssClassMap = {
        default: 'color-ui-neutral-text bg-color-ui-neutral',
        confirm: 'color-ui-confirm-text bg-color-ui-confirm',
        info: 'color-ui-info-text bg-color-ui-info',
        warning: 'color-ui-warning-text bg-color-ui-warning',
        error: 'color-ui-error-text bg-color-ui-error'
      }

      return cssClassMap[this.color]
    },

    sizeClasses () {
      const cssClassMap = {
        small: 'font-size-small u-pv-0_125 u-ph-0_25',
        medium: 'font-size-medium u-pv-0_25 u-ph-0_5',
        large: 'font-size-large u-pv-0_5 u-ph-0_75'
      }

      return cssClassMap[this.size]
    }
  }
}
</script>
