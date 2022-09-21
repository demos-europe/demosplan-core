<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-mb-0_75">
    <!-- insert form fields here -->
    <slot />
  </div>
</template>

<script>
export default {
  name: 'DpFormRow',

  data () {
    return {
      // Number of elements in the form row
      elementCount: 0,
      // True if the form elements fill the entire row
      isFullRow: true
    }
  },

  computed: {
    /**
     * Array containing the widths of the form elements
     * @return {Array}
     */
    elementsWidths () {
      return this.$children.map(child => child.$props.width || 'u-1-of-1') || []
    }
  },

  methods: {
    /**
     * Determine if the given form elements fill the entire row; if not, add padding-right
     */
    determineRowWidth () {
      if (this.elementsWidths.length > 0) {
        // U-firstWidthValue-of-y
        const firstWidthValues = this.elementsWidths.map(width => parseInt(width.match(/\d+/)))
        // Add firstWidthValues together
        const combinedWidth = firstWidthValues.reduce((acc, curr) => {
          return acc + parseInt(curr)
        })
        // U-x-of-completeWidth
        const completeWidth = parseInt(this.elementsWidths[0].match(/\d+$/)[0])
        this.isFullRow = completeWidth === combinedWidth
      }
    },

    /**
     * Add padding-right to the form element if
     * - it is not the last element in the row or
     * - it is the last element in the row, but the elements don't fill the row
     */
    setPadding () {
      this.$children.forEach(child => {
        const idx = this.$children.indexOf(child)
        if (idx < this.elementCount - 1 || (idx === this.elementCount - 1 && this.isFullRow === false)) {
          child.$el.classList.add('u-pr')
        }
      })
    }
  },

  mounted () {
    this.elementCount = this.$children.length
    this.determineRowWidth()
    this.setPadding()
  }
}
</script>
