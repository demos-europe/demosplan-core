<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="prefixClass('o-form__element--checkbox')">
    <input
      :id="id"
      :name="name !== '' ? name : false"
      :class="prefixClass('o-form__control-input')"
      type="checkbox"
      :required="required"
      :disabled="disabled"
      :readonly="readonly"
      autocomplete="off"
      :checked="checked"
      :value="valueToSend"
      @change="$emit('change', $event.target.checked)"
      true-value="1"
      false-value="0">
    <dp-label
      v-if="label.text !== ''"
      :class="prefixClass('o-form__label')"
      v-bind="{
        bold: false,
        text: '',
        for: id,
        required: required,
        ...label,
      }" />
  </div>
</template>

<script>
import { DpLabel } from 'demosplan-ui/components'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpCheckbox',

  components: {
    DpLabel
  },

  mixins: [prefixClassMixin],

  model: {
    prop: 'checked',
    event: 'change'
  },

  props: {
    checked: {
      type: Boolean,
      required: false,
      default: false
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    id: {
      type: String,
      required: true
    },

    label: {
      type: Object,
      default: () => ({}),
      validator: (prop) => {
        return Object.keys(prop).every(key => ['bold', 'hint', 'text', 'tooltip'].includes(key))
      }
    },

    name: {
      type: String,
      required: false,
      default: ''
    },

    readonly: {
      type: Boolean,
      required: false,
      default: false
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    valueToSend: {
      type: String,
      required: false,
      default: '1'
    }
  }
}
</script>
