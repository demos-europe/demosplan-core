<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <span
    :style="{height: height, width: width}"
    class="c-skeleton-box" />
</template>

<script>
export default {
  name: 'DpSkeletonBox',

  props: {
    height: {
      type: String,
      default: '1em'
    },

    width: {
      type: String,
      default: '100%'
    }
  }
}
</script>
