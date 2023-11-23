<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li>
    <input
      :id="`${instance}_id_${option.id}`"
      :checked="checked"
      :name="`${instance}_name_${option.id}`"
      type="checkbox"
      @change="toggle">
    <label
      :class="{'weight--normal': highlight === false }"
      class="inline-block u-m-0"
      :for="`${instance}_id_${option.id}`"
      v-tooltip="option.attributes.description">
      {{ option.attributes.label }} <template v-if="showCount">({{ option.attributes.count }})</template>
    </label>
  </li>
</template>

<script>
export default {
  name: 'FilterFlyoutCheckbox',

  props: {
    checked: {
      type: Boolean,
      required: false,
      default: false
    },

    highlight: {
      type: Boolean,
      required: false,
      default: false
    },

    instance: {
      type: String,
      required: false,
      default: 'list'
    },

    option: {
      type: Object,
      required: true
    },

    showCount: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  methods: {
    toggle () {
      this.$emit('change', !this.checked, this.option)
    }
  }
}
</script>
