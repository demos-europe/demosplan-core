<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li>
    <input
      :data-cy="`flyoutOption:${instance}:${option.id}`"
      :id="`${instance}_id_${option.id}`"
      :checked="checked"
      :name="`${instance}_name_${option.id}`"
      type="checkbox"
      @change="toggle">
    <label
      :class="{'weight--normal': highlight === false }"
      class="inline-block u-m-0"
      :for="`${instance}_id_${option.id}`">
      {{ option.label }} <template v-if="showCount">({{ option.count }})</template>
    </label>
    <dp-contextual-help
      v-if="option.description && instance !=='itemsSelected'"
      class="float-right mt-0.5"
      :text="option.description" />
  </li>
</template>

<script>
import { DpContextualHelp } from '@demos-europe/demosplan-ui'

export default {
  name: 'FilterFlyoutCheckbox',

  components: {
    DpContextualHelp
  },

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

    /**
     * { id: string, label: string, selected: boolean }
     */
    option: {
      type: Object,
      required: true
    },

    showCount: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  methods: {
    toggle () {
      this.$emit('change', !this.checked, this.option)
    }
  }
}
</script>
