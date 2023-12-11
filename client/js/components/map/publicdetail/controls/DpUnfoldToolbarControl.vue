<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!--
        # component behaves as drag-handle for an element defined in the data-target-prop
        # the handler can be overwritten by using the slot
     -->
    <usage>
        <dp-unfold-toolbar-control
            :drag-target="prefixClass('.query-selector')"
            direction="left"
        ></dp-unfold-toolbar-control>
    </usage>
</documentation>

<template>
  <div
    :class="prefixClass('c-map__unfold-button')"
    @mousedown.prevent="startDrag"
    @touchstart="startDrag"
    draggable="true">
    <slot>
      <div :class="prefixClass('c-map__unfold-button-inner')">
        <i
          aria-hidden="true"
          :class="prefixClass('c-map__unfold-button-handle')" />
      </div>
    </slot>
  </div>
</template>

<script>
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpUnfoldToolbarControl',

  mixins: [prefixClassMixin],

  props: {
    dragTarget: {
      required: false,
      type: String,
      default: '#app'
    },

    // This number is used to have a "margin" for the max-value. So we prevent a scrollbar and can see the handle at max-scale
    magicNumber: {
      required: false,
      type: Number,
      default: 100
    },

    direction: {
      required: false,
      type: String,
      default: 'right'
    }
  },

  emits: [
    'toolbar:drag'
  ],

  data () {
    return {
      unfolded: false,
      target: null,
      initialSize: 0,
      currentSize: 0,
      maxSize: 0
    }
  },

  computed: {
    /**
     * Check if it has to calculate the size or the height
     */
    dimension () {
      return (this.direction === 'left' || this.direction === 'right') ? 'width' : 'height'
    },

    dimensionPadding () {
      return (this.direction === 'left' || this.direction === 'right') ? ['paddingRight', 'paddingLeft'] : ['paddingTop', 'paddingBottom']
    },

    parentPadding () {
      return parseInt(getComputedStyle(this.target.parentElement)[this.dimensionPadding[0]].slice(0, -2)) + parseInt(getComputedStyle(this.target.parentElement)[this.dimensionPadding[1]].slice(0, -2))
    }
  },

  methods: {
    /**
     * Check if the new size is valid. Otherwise use min/max values.
     */
    handleDrag (event) {
      let newSize
      if (this.dimension === 'height') {
        if (event.type === 'mousemove') {
          newSize = event.clientY - this.target.getBoundingClientRect().top - this.parentPadding
        } else {
          newSize = event.changedTouches[0].clientY - this.target.getBoundingClientRect().top - this.parentPadding
        }
      } else {
        if (event.type === 'mousemove') {
          newSize = event.clientX - this.target.getBoundingClientRect().left - this.parentPadding
        } else {
          newSize = event.changedTouches[0].clientX - this.target.getBoundingClientRect().left - this.parentPadding
        }
      }

      if (newSize < (this.initialSize)) {
        newSize = (this.initialSize)
      } else if (newSize > this.maxSize) {
        newSize = this.maxSize
      }
      this.setNewSize(newSize)
    },

    /**
     * Remove manually set size if the page gets resized
     */
    handleResize () {
      this.target.removeAttribute('style')
      this.setMaxSize()
    },

    /**
     * Calculate the max-size by getting the parent.size and removing the padding
     */
    setMaxSize () {
      const containerDimensions = {
        width: this.target.parentElement.offsetWidth,
        height: this.target.parentElement.offsetHeight
      }
      this.maxSize = containerDimensions[this.dimension] - this.magicNumber - this.parentPadding
    },

    /**
     * Set size of the parent
     */
    setNewSize (newSize) {
      this.currentSize = newSize
      this.target.setAttribute('style', this.dimension + ': ' + newSize + 'px')
      this.target.style.background = 'white'
      this.target.style.zIndex = '10'
      this.$root.$emit('toolbar:drag')
    },

    /**
     * Add EventListener to document when mouse-click/touch on drag-handle
     */
    startDrag () {
      window.addEventListener('mousemove', this.handleDrag)
      window.addEventListener('touchmove', this.handleDrag)
    },

    /**
     * Remove EventListener on release to avoid too much event listening
     */
    stopDrag () {
      window.removeEventListener('mousemove', this.handleDrag)
      window.removeEventListener('touchmove', this.handleDrag)
    }
  },

  mounted () {
    // Get dom reference to drag target
    this.target = document.querySelector(this.dragTarget)

    // Mouseup EventListener to detect end of dragging
    window.addEventListener('mouseup', this.stopDrag)
    window.addEventListener('touchend', this.stopDrag)

    // Listen for the resize of the page to know if the max-size has to be recalculated.
    window.addEventListener('resize', this.handleResize)

    // Check if size has to be recalculated after tab-use
    document.querySelector('[href="#procedureDetailsMap"]').addEventListener('click', this.setMaxWidth)

    // Set initial values for min/max size
    this.initialSize = parseInt(getComputedStyle(this.target)[this.dimension].slice(0, -2))
    this.currentSize = this.initialSize
    this.setMaxSize()
  },

  beforeUnmount () {
    // Remove event listener if the component gets destroyed
    window.removeEventListener('mouseup', this.stopDrag)
    window.removeEventListener('touchend', this.stopDrag)
    window.removeEventListener('resize', this.handleResize)
  }
}
</script>
