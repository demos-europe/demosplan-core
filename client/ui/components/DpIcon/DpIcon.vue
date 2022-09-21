<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <svg
    class="c-icon"
    :height="pxSize"
    :width="pxSize"
    viewBox="0 0 16 16"
    xmlns="http://www.w3.org/2000/svg">
    <path :d="path" />
  </svg>
</template>

<script>
import { ICONS, SIZES } from './util/iconVariables'

export default {
  name: 'DpIcon',

  props: {
    icon: {
      type: String,
      required: true
    },

    size: {
      type: String,
      required: false,
      default: 'medium',
      validator: (prop) => Object.keys(SIZES).includes(prop)
    }
  },

  computed: {
    pxSize () {
      return SIZES[this.size]
    },

    path () {
      return ICONS[this.icon].path
    }
  }
}
</script>
