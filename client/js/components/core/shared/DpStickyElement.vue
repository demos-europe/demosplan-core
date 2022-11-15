<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    :class="{'o-sticky--border': border, 'u-z-fixed': applyZIndex}"
    class="o-sticky bg-color--white">
    <slot />
  </div>
</template>

<script>
import { Stickier } from 'demosplan-utils'

export default {
  name: 'DpStickyElement',

  props: {
    /**
     * If set to false, no z-index is applied to the element.
     */
    applyZIndex: {
      required: false,
      type: Boolean,
      default: true
    },

    /**
     * Whether the element should show an 1px border when being sticky.
     * When enabled, the border is always displayed on the side of the
     * element opposite to the direction the element sticks to.
     */
    border: {
      required: false,
      type: Boolean,
      default: false
    },

    /**
     * Reference to the HTMLElement that should serve as the context for the sticky element.
     * If omitted, the Stickier lib will default to this.$el.offsetParent
     */
    context: {
      type: HTMLElement,
      required: false,
      default: undefined
    },

    /**
     * The direction the element should stick to.
     */
    direction: {
      required: false,
      type: String,
      default: 'top'
    },

    /**
     * Whether context changes should trigger a refresh of stickier positions.
     */
    observeContext: {
      required: false,
      type: Boolean,
      default: true
    },

    /**
     * The offset from context the element should go into/out of sticky mode.
     */
    offset: {
      required: false,
      type: Number,
      default: 0
    }
  },

  mounted () {
    this.stickyElement = new Stickier(
      this.$el,
      this.context,
      this.offset,
      this.direction,
      'palm',
      false,
      this.observeContext
    )
  }
}
</script>
