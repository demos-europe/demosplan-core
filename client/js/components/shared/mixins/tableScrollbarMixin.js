/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
export default {
  methods: {
    /**
     * Adjust the width of the inner element of the footer scrollbar to the width of the Table.
     */
    updateScrollbarStyles () {
      const tableWidth = window.getComputedStyle(this.dataTableElement).width
      const tableContainerWidth = window.getComputedStyle(this.dataTableContainerElement).width

      if (tableWidth > tableContainerWidth) {
        this.scrollbar.classList.remove('hidden')
        this.scrollbar.firstChild.setAttribute('style', 'width:' + tableWidth + ';height:1px;')
      } else {
        this.scrollbar.classList.add('hidden')
      }
    }
  },

  created () {
    /**
     * Updating the scrollbar needs to wait for the dataTable items to load,
     * as the table is only then present in its final width.
     */
    this.$watch('isLoading', (isLoading) => {
      if (isLoading) {
        return
      }

      this.$nextTick(() => {
        this.scrollbar = this.$refs.scrollBar
        this.dataTableContainerElement = this.$refs.dataTable.$el
        this.dataTableElement = this.$refs.dataTable.$refs.tableEl

        // Bind behaviour and position of the footer scrollbar to the scroll position of the dataTableContainerElement.
        this.scrollbar.addEventListener('scroll', () => {
          this.dataTableContainerElement.scrollLeft = this.scrollbar.scrollLeft
        })

        this.dataTableContainerElement.addEventListener('scroll', () => {
          this.scrollbar.scrollLeft = this.dataTableContainerElement.scrollLeft
        })

        // Observe changes to dataTable to update scrollbar accordingly
        this.dataTableObserver = new ResizeObserver(this.updateScrollbarStyles.bind(this))
        this.dataTableObserver.observe(this.dataTableElement)

        // Set scrollbar width or conditionally hide it.
        this.updateScrollbarStyles()
      })
    })
  }
}
