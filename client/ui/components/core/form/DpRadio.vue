<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="prefixClass('o-form__element--radio')">
    <input
      :id="id"
      :name="name !== '' ? name : false"
      :class="prefixClass('o-form__control-input')"
      type="radio"
      :required="required"
      :disabled="disabled"
      :readonly="readonly"
      autocomplete="off"
      :checked="checked"
      :value="value"
      :data-cy="dataCy !== '' ? dataCy : false"
      @change="$emit('change', $event.target.checked)"><!--
 --><dp-label
      :class="prefixClass('o-form__label')"
      v-bind="labelProps" />
  </div>
</template>

<script>
import { DpLabel } from 'demosplan-ui/components'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpRadio',

  components: {
    DpLabel
  },

  mixins: [prefixClassMixin],

  model: {
    prop: 'checked',
    event: 'change'
  },

  props: {
    bold: {
      type: Boolean,
      required: false,
      default: true
    },

    checked: {
      type: Boolean,
      required: false,
      default: false
    },

    dataCy: {
      type: String,
      required: false,
      default: ''
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    hint: {
      type: String,
      required: false,
      default: ''
    },

    id: {
      type: String,
      required: true
    },

    label: {
      type: String,
      required: false,
      default: ''
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

    value: {
      type: String,
      required: false,
      default: '1'
    }
  },

  computed: {
    labelProps () {
      return {
        for: this.id,
        hint: this.hint,
        text: this.label,
        required: this.required,
        bold: this.bold
      }
    }
  }
}
</script>
