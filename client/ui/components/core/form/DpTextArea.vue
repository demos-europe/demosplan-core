<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="{ 'flex flex-column': growToParent }">
    <dp-label
      v-if="label !== ''"
      v-bind="labelProps" /><!--
 --><textarea
      :name="name"
      :id="id"
      class="o-form__control-textarea"
      :class="{ 'flex-grow': growToParent, 'height-60': reducedHeight }"
      :data-dp-validate-if="dataDpValidateIf"
      :disabled="disabled"
      :maxlength="maxlength"
      v-bind="allowedAttributes"
      v-model="currentValue"
      @input="$emit('input', currentValue)"
      :required="required" />
  </div>
</template>

<script>
import { attributes, length } from 'demosplan-ui/shared/props'
import { maxlengthHint } from 'demosplan-ui/utils/lengthHint'

export default {
  name: 'DpTextArea',

  components: {
    DpLabel: async () => {
      const { DpLabel } = await import('demosplan-ui/components')
      return DpLabel
    }
  },

  props: {
    attributes: attributes('textarea'),

    /**
     * Use to conditionally validate a required textarea field.
     */
    dataDpValidateIf: {
      type: [Boolean, String],
      required: false,
      default: false
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * If enabled, classes are applied to let element grow to 100% height of its parent element.
     */
    growToParent: {
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
      required: false,
      default: ''
    },

    label: {
      type: String,
      required: false,
      default: ''
    },

    maxlength: length,

    name: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Display the form field with reduced height (60px).
     */
    reducedHeight: {
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
      default: ''
    }
  },

  data () {
    return {
      currentValue: this.value
    }
  },

  computed: {
    allowedAttributes () {
      const attrs = {}
      this.attributes.forEach(attr => {
        attr = attr.split('=')
        attrs[attr[0]] = attr[1]
      })
      return attrs
    },

    labelProps () {
      return {
        for: this.id,
        hint: this.maxlength ? [this.hint, maxlengthHint(this.currentValue.length, this.maxlength)] : this.hint,
        required: this.required,
        text: this.label,
        tooltip: this.tooltip
      }
    }
  },

  watch: {
    value () {
      this.currentValue = this.value
    }
  }
}
</script>
