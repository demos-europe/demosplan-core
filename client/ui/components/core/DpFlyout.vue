<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--

The DpFlyout component renders a flyout that is intended to show stuff.

@improve use https://popper.js.org/docs/v2/ for positioning instead of basic css - or find
another way to make left/right offset dynamic.

-->
<usage variant="TBD">
</usage>
</documentation>

<template>
  <span
    class="o-flyout"
    :class="{
      'o-flyout--left': align === 'left',
      'o-flyout--right': align === 'right',
      'o-flyout--padded': padded,
      'is-expanded': isExpanded,
      'o-flyout--menu': hasMenu
    }"
    v-click-outside="close"
    data-cy="flyoutTrigger">
    <button
      :disabled="disabled"
      type="button"
      aria-haspopup="true"
      class="o-flyout__trigger btn--blank o-link--default u-ph-0_25 line-height--2 whitespace--nowrap"
      @click="toggle">
      <slot name="trigger">
        <i class="fa fa-ellipsis-h" />
      </slot>
    </button>
    <div
      class="o-flyout__content box-shadow-1"
      data-cy="flyout">
      <slot />
    </div>
  </span>
</template>

<script>
import ClickOutside from 'vue-click-outside'

export default {
  name: 'DpFlyout',

  directives: {
    ClickOutside
  },

  props: {
    align: {
      required: false,
      type: String,
      default: 'right',
      validator: (prop) => ['left', 'right'].includes(prop)
    },

    disabled: {
      required: false,
      type: Boolean,
      default: false
    },

    hasMenu: {
      required: false,
      type: Boolean,
      default: true
    },

    padded: {
      required: false,
      type: Boolean,
      default: true
    }
  },

  data () {
    return {
      isExpanded: false
    }
  },

  methods: {
    close () {
      if (this.isExpanded === true) {
        this.$emit('close')
        this.isExpanded = false
      }
    },

    toggle () {
      this.isExpanded = !this.isExpanded

      if (this.isExpanded) {
        this.$emit('open')
      } else {
        this.$emit('close')
      }
    }
  },

  mounted () {
    this.popupItem = this.$el
  }
}
</script>
