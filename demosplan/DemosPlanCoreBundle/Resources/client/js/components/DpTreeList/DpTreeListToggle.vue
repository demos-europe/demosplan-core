<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button
    type="button"
    class="o-link--default btn--blank"
    :aria-label="label"
    @click="toggle">
    <i
      :class="iconClass"
      aria-hidden="true" />
  </button>
</template>

<script>
export default {
  name: 'DpTreeListToggle',

  props: {
    value: {
      type: Boolean,
      required: false,
      default: false
    },

    iconClassProp: {
      type: String,
      required: false,
      default: ''
    },

    toggleAll: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  computed: {
    /*
     * May be passed from outside, but defaults to angle icon controlled by state.
     * This is somewhat messy but removes cruft from DpTreeListNode.
     */
    iconClass () {
      return this.iconClassProp !== ''
        ? this.iconClassProp
        : ('font-size-large line-height--1 u-p-0_25 fa ' + (this.value ? 'fa-angle-up' : 'fa-angle-down'))
    },

    label () {
      // Here, the relatively generic term "element" is chosen to keep the wording generic.
      return this.toggleAll
        ? Translator.trans(this.value ? 'aria.collapse.all' : 'aria.expand.all')
        : Translator.trans(this.value ? 'aria.collapse' : 'aria.expand')
    }
  },

  methods: {
    toggle () {
      this.$emit('input', !this.value)
    }
  }
}
</script>
