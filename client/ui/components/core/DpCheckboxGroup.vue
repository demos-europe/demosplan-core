<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset class="u-pb-0">
    <legend
      v-if="label !== ''"
      v-cleanhtml="label"
      class="font-size-medium is-label"
      :class="inline ? 'float--left' : 'u-mb-0_25'" />
    <dp-checkbox
      v-for="(option, idx) in options"
      :id="option.id"
      :key="`option_${idx}`"
      v-model="selected[option.id]"
      :class="inline ? 'display--inline-block u-ml' : ''"
      :label="{
        text: option.label
      }"
      :name="option.name || ''"
      @change="$emit('update', selected)" />
  </fieldset>
</template>

<script>
import { CleanHtml } from 'demosplan-ui/directives'
import DpCheckbox from './form/DpCheckbox'

export default {
  name: 'DpCheckboxGroup',

  components: {
    DpCheckbox
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    options: {
      type: Array,
      required: true
    },

    label: {
      type: String,
      required: false,
      default: ''
    },

    inline: {
      type: Boolean,
      default: false
    },

    selectedOptions: {
      type: Object,
      default: () => ({})
    }
  },

  data () {
    return {
      selected: {}
    }
  },

  watch: {
    selectedOptions () {
      this.selected = this.selectedOptions
    }
  },

  methods: {
    setSelected () {
      this.options.forEach(option => {
        Vue.set(this.selected, option.id, false)
      })
    }
  },

  mounted () {
    this.setSelected()
  }
}
</script>
