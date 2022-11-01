<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <label class="u-mb-0">
    <input
      class="u-m-0_5 u-ml-0_25"
      type="checkbox"
      :name="name"
      :id="name"
      :checked="checked"
      :value="stringValue"
      @click="check">
    <span class="hide-visually">{{ label }}</span>
  </label>
</template>

<script>
export default {
  name: 'DpTreeListCheckbox',

  model: {
    prop: 'checked',
    event: 'check'
  },

  props: {
    checked: {
      type: Boolean,
      required: false,
      default: false
    },

    checkAll: {
      type: Boolean,
      required: false,
      default: false
    },

    name: {
      type: String,
      required: true
    },

    // Some implementations may require to set a custom value, eg. when submitting DpTreeList as a whole form.
    stringValue: {
      type: String,
      required: false,
      default: 'on'
    }
  },

  computed: {
    label () {
      if (this.checkAll) {
        return this.toggleCheckedStatus('aria.deselect_all', 'aria.select.all')
      }

      return this.toggleCheckedStatus('aria.deselect', 'aria.select')
    }
  },

  methods: {
    check () {
      this.$emit('check', !this.checked)
    },

    toggleCheckedStatus (deselect, select) {
      return Translator.trans(this.checked ? deselect : select)
    }
  }
}
</script>
