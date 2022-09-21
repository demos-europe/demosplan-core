<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--
An Inputfield wich comes with an dismiss-icon if there is some Text typed.

Clicking the dismiss-"x" emits "reset".

To provide the interface for dpInput all configuration attributes for the input can be passed via `inputAttributes`.

-->
</documentation>

<template>
  <div class="position--relative">
    <dp-input
      :id="id"
      :data-cy="dataCy"
      has-icon
      :required="required"
      v-bind="inputAttributes"
      @blur="$emit('blur', currentValue)"
      @input="$emit('input', currentValue)"
      @enter="$emit('enter', currentValue)"
      @focus="$emit('focus')"
      :pattern="pattern"
      v-model="currentValue" />
    <button
      v-if="!inputAttributes.disabled"
      class="btn--blank o-link--default"
      :class="buttonClass"
      :disabled="currentValue === defaultValue"
      @click="resetValue">
      <dp-icon
        icon="xmark"
        :size="iconSize" />
    </button>
  </div>
</template>

<script>
import { DpIcon, DpInput } from 'demosplan-ui/components'

export default {
  name: 'DpResettableInput',

  components: {
    DpIcon,
    DpInput
  },

  props: {
    dataCy: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * By default, the normal variant is used. If set to 'small', a smaller variant is displayed
     */
    buttonVariant: {
      type: String,
      required: false,
      default: 'medium',
      validator: (prop) => ['small', 'medium'].includes(prop)
    },

    defaultValue: {
      type: String,
      required: false,
      default: ''
    },

    id: {
      type: String,
      required: true
    },

    inputAttributes: {
      type: Object,
      required: false,
      default: () => ({})
    },

    pattern: {
      type: String,
      required: false,
      default: ''
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    value: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentValue: this.value
    }
  },

  computed: {
    buttonClass () {
      return this.buttonVariant === 'small' ? 'o-form__control-search-reset--small' : 'o-form__control-search-reset'
    },

    iconSize () {
      return this.buttonVariant
    }
  },

  watch: {
    value: function () {
      this.currentValue = this.value
    }
  },

  methods: {
    resetValue () {
      this.$emit('reset')
    }
  }
}
</script>
