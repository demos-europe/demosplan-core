<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li>
    <input
      type="checkbox"
      :checked="checked"
      @change="toggle"
      :id="`${instance}_id_${option.id}`"
      :name="`${instance}_name_${option.id}`">
    <label
      :class="{'weight--normal': highlight === false }"
      class="inline-block u-m-0"
      :for="`${instance}_id_${option.id}`">
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
