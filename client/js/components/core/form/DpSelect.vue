<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-label
      v-if="label.text"
      v-bind="{
        ...label,
        for: nameOrId,
        required: required
      }" /><!--
 --><select
      :id="nameOrId"
      :required="required"
      :name="name !== '' ? name : false"
      class="o-form__control-select"
      :class="[disabled ? ' bg-color--grey-light-2' : '', classes]"
      :disabled="disabled"
      @change="update">
      <option
        v-if="showPlaceholder"
        data-id="placeholder"
        disabled
        value=""
        :selected="selected === ''">
        {{ Translator.trans(placeholder) }}
      </option>
      <option
        v-for="(option, idx) in options"
        :selected="option.value === selected"
        :value="option.value"
        :key="idx">
        {{ Translator.trans(option.label) }}
      </option>
    </select>
  </div>
</template>

<script>
import { DpLabel } from 'demosplan-ui/components'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpSelect',

  components: {
    DpLabel
  },

  mixins: [prefixClassMixin],

  /*
   * Customize the v-model binding names
   * see https://learn.adamwathan.com/advanced-vue/customizing-controlled-component-bindings
   */
  model: {
    prop: 'selected',
    event: 'select'
  },

  props: {
    classes: {
      type: [Array, String],
      required: false,
      default: ''
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    id: {
      type: String,
      required: false,
      default: () => ''
    },

    label: {
      type: Object,
      required: false,
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

    // Need label and value
    options: {
      required: true,
      type: Array
    },

    placeholder: {
      type: String,
      required: false,
      default: 'warning.select.entry'
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    showPlaceholder: {
      type: Boolean,
      required: false,
      default: true
    },

    selected: {
      type: String,
      required: false,
      default: ''
    }
  },

  computed: {
    nameOrId () {
      /*
       * As long as there is no necessity of having the id to differ from name,
       * it should not be required to specify it.
       */
      return this.id === '' ? this.name : this.id
    }
  },

  methods: {
    update (event) {
      this.$emit('select', event.target.value)
    }
  }
}
</script>
