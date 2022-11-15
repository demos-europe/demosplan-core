<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    It renders an fontawesome Icon with a tooltip
  -->
  <usage>
    <dp-tooltip-icon
      icon="fa-trash"
      text="some translated String"
      large="true|false (adds the class fa-lg)"
    ></dp-tooltip-icon>
  </usage>
</documentation>

<template>
  <i
    class="fa"
    :class="[icon, large ? 'fa-lg' : '']"
    v-tooltip="text" />
</template>

<script>
export default {
  name: 'DpTooltipIcon',

  props: {
    icon: {
      required: true,
      type: String
    },

    text: {
      required: true,
      type: String
    },

    large: {
      required: false,
      type: Boolean,
      default: false
    }
  }
}
</script>
