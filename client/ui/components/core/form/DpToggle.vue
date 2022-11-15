<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    Simple Toggle component

    @improve T14353 rename to DpToggle
  -->
</documentation>

<template>
  <span
    class="toggle-wrapper"
    @click="toggle"
    role="checkbox"
    :aria-checked="value.toString()"
    :aria-disabled="disabled"
    tabindex="0"
    @keydown.space.prevent="toggle">
    <div
      v-show="disabled"
      class="toggle-disabled" />
    <span
      class="toggle-background"
      :style="backgroundStyles" />
    <span
      class="toggle-indicator"
      :style="indicatorStyles" />
  </span>
</template>

<script>
// Simple Toggle by Adam Wathan https://jsfiddle.net/adamwathan/hfs34ye4/
export default {
  name: 'DpToggle',

  props: {
    value: {
      type: Boolean,
      required: false,
      default: false
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  computed: {
    backgroundStyles () {
      return {
        backgroundColor: this.value ? '#3490dc' : '#dae1e7'
      }
    },

    indicatorStyles () {
      return { transform: this.value ? 'translateX(1rem)' : 'translateX(0)' }
    }
  },

  methods: {
    toggle () {
      if (this.disabled === false) {
        this.$emit('input', !this.value)
      }
    }
  }
}
</script>
