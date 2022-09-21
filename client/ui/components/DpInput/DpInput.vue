<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="containerWidth !== '' ? prefixClass(containerWidth) : false">
    <dp-label
      v-if="label.text !== ''"
      v-bind="{
        ...label,
        for: id,
        hint: labelHint,
        required: required
      }" /><!--
 --><input
      :id="id"
      :name="name !== '' ? name : false"
      :class="prefixClass(classes)"
      :data-counter="dataCounter !== '' ? dataCounter : false"
      :data-dp-validate-error="dataDpValidateError || false"
      :data-dp-validate-if="dataDpValidateIf !== '' ? dataDpValidateIf : false"
      :data-dp-validate-should-equal="dataDpValidateShouldEqual !== '' ? dataDpValidateShouldEqual : false"
      :data-cy="dataCy !== '' ? dataCy : false"
      :aria-labelledby="ariaLabelledby"
      :maxlength="maxlength !== '' ? maxlength : false"
      :minlength="minlength !== '' ? minlength : false"
      :type="type"
      :pattern="pattern !== '' ? pattern : false"
      :placeholder="placeholder !== '' ? placeholder : false"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :autocomplete="autocomplete !== '' ? autocomplete : false"
      :size="size ? size : false"
      v-model="currentValue"
      @focus="$emit('focus')"
      @input="$emit('input', currentValue)"
      @keydown.enter="handleEnter">
  </div>
</template>

<script>
import { exactlengthHint, maxlengthHint, minlengthHint } from '../../utils/lengthHint'
import DpLabel from '../DpLabel/DpLabel'
import { length } from '../../shared/props'
import { prefixClassMixin } from '../../mixins'

export default {
  name: 'DpInput',

  components: {
    DpLabel
  },

  mixins: [prefixClassMixin],

  props: {
    /**
     * Reference another element on the page to define an accessible name if there is no label or
     * you want to override the label.
     */
    ariaLabelledby: {
      type: [String, Boolean],
      required: false,
      default: false
    },

    /**
     * Tell the browser if autocomplete is allowed or not. If enabled the browser is allowed
     * to automatically complete the input. You can also provide a type of data which is expected.
     */
    autocomplete: {
      type: String,
      required: false,
      default: ''
    },

    dataCounter: {
      type: String,
      required: false,
      default: ''
    },

    dataCy: {
      type: String,
      required: false,
      default: ''
    },

    dataDpValidateError: {
      type: String,
      required: false,
      default: ''
    },

    dataDpValidateIf: {
      type: String,
      required: false,
      default: ''
    },

    dataDpValidateShouldEqual: {
      type: String,
      required: false,
      default: ''
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Use: when input field used with Icon || Button, then input field has padding right.
     */
    hasIcon: {
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
      default: () => ({
        bold: true,
        hint: '',
        text: '',
        tooltip: ''
      }),
      validator: (prop) => {
        return Object.keys(prop).every(key => ['bold', 'hint', 'text', 'tooltip'].includes(key))
      }
    },

    /**
     * Limit the maximum allowed number of characters to the given amount.
     */
    maxlength: length,

    /**
     * Define the minimum number of characters that need to be given.
     */
    minlength: length,

    name: {
      type: String,
      required: false,
      default: ''
    },

    pattern: {
      type: String,
      required: false,
      default: ''
    },

    placeholder: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Set to false to prevent default behavior onEnter.
     */
    preventDefaultOnEnter: {
      type: Boolean,
      required: false,
      default: true
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

    /**
     * When setting a number for the `size` prop, this is directly rendered
     * as html attribute on the input element. Also, it is assumed that visual sizing
     * based on that value shall be applied, that is why both container classes
     * and element classes do not define any width styles when a size is set here.
     */
    size: {
      type: Number,
      required: false,
      default: undefined
    },

    type: {
      type: String,
      required: false,
      default: 'text'
    },

    value: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Full width by default; set to 'auto' to have no width defined.
     * @deprecated Apply width to the parent element of DpInput.
     */
    width: {
      type: String,
      default: 'u-1-of-1'
    }
  },

  data () {
    return {
      currentValue: this.value
    }
  },

  computed: {
    classes () {
      let cssClasses = []
      cssClasses = (this.size > 0) ? [] : [...cssClasses, 'u-1-of-1']
      cssClasses = this.readonly || this.disabled ? [...cssClasses, 'bg-color--grey-light-2'] : cssClasses
      cssClasses = this.type !== 'search' ? [...cssClasses, 'o-form__control-input'] : [...cssClasses, 'o-form__control-search']
      cssClasses = this.hasIcon ? [...cssClasses, 'u-pr'] : cssClasses
      return cssClasses.join(' ')
    },

    containerWidth () {
      return (this.width === 'auto' || this.size > 0) ? '' : this.width
    },

    labelHint () {
      const hint = typeof this.label.hint !== 'undefined' && this.label.hint !== '' ? [this.label.hint] : []

      if (this.maxlength && !this.minlength) {
        hint.push(maxlengthHint(this.currentValue.length, this.maxlength))
      } else if (this.minlength && !this.maxlength) {
        hint.push(minlengthHint(this.currentValue.length, this.minlength))
      } else if (this.maxlength && this.minlength) {
        if (this.maxlength === this.minlength) {
          hint.push(exactlengthHint(this.currentValue.length, this.maxlength))
        } else {
          hint.push(maxlengthHint(this.currentValue.length, this.maxlength))
          hint.push(minlengthHint(this.currentValue.length, this.minlength))
        }
      }

      return hint
    }
  },

  watch: {
    value: function () {
      this.currentValue = this.value
    }
  },

  methods: {
    handleEnter (event) {
      if (this.preventDefaultOnEnter === true) {
        event.preventDefault()
      }
      this.$emit('enter')
    }
  }
}
</script>
