/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
/**
 * This mixin is intended to show a fixed scrollbar below a dataTable that exceeds its container.
 * Example usage:
 *
 * <dp-data-table
 *   ref="dataTable"
 *   class="overflow-x-auto"
 *   <!-- other required attrs for dataTable... --> />
 * <div
 *   ref="scrollBar"
 *   class="sticky bottom-0 left-0 right-0 -mt-3 overflow-x-scroll overflow-y-hidden">
 *   <div :style="scrollbarInnerStyle" />
 * </div>
 *
 * Important thing to note is that both elements should have exactly the refs shown in the example.
 * Also, within the using component, `isLoading` should be present within data.
 */
export default {
  data () {
    return {
      scrollbarInnerStyle: {
        height: '1px',
        width: '0px',
      },
      scrollbarVisible: false,
    }
  },

  methods: {
    /**
     * Adjust the width of the inner element of the footer scrollbar to the width of the Table,
     * conditionally hide or show scrollbar.
     */
    updateScrollbarStyles () {
      const scrollWidth = this.dataTableContainerElement.scrollWidth
      const clientWidth = this.dataTableContainerElement.clientWidth

      if (scrollWidth > clientWidth) {
        this.scrollbarVisible = true
        this.scrollbarInnerStyle = {
          height: '1px',
          minWidth: scrollWidth + 'px',
          width: scrollWidth + 'px',
        }
        this.dataTableContainerElement.classList.add('has-scrollable-content')
      } else {
        this.scrollbarVisible = false
        this.dataTableContainerElement.classList.remove('has-scrollable-content')
      }
    },

    /**
     * Calculate and set the max height of the scroll container so it fits within the viewport.
     * Only applies when a ref="scrollContainer" is present.
     */
    updateScrollContainerHeight () {
      if (!this.$refs?.scrollContainer) {
        return
      }

      const top = this.$refs.scrollContainer.getBoundingClientRect().top
      /*
       * In fullscreen, the scrollbar is position: fixed at bottom-3 (12px), with height h-3 (12px) = 24px total.
       * Outside fullscreen, 18px reserves space for the sticky scrollbar.
       */
      const offset = this.isFullscreen ? 24 : 18
      this.$refs.scrollContainer.style.maxHeight = `calc(100vh - ${top}px - ${offset}px)`
    },
  },

  created () {
    /**
     * When fullscreen mode is toggled, recalculate the scroll container height.
     * Only has effect in components that also use fullscreenModeMixin (isFullscreen exists).
     */
    this.$watch('isFullscreen', () => {
      if (!this.$refs?.scrollContainer) {
        return
      }

      this.$nextTick(() => this.updateScrollContainerHeight())
    })

    this.$watch('isLoading', (isLoading) => {
      if (isLoading) {
        return
      }

      this.$nextTick(() => {
        this.scrollbar = this.$refs?.scrollBar
        /*
         * Allows components to use a separate scroll wrapper (ref="scrollContainer") to enable sticky headers,
         * while keeping the dataTable itself free of overflow that would block position: sticky.
         */
        this.dataTableContainerElement = this.$refs?.scrollContainer ?? this.$refs?.dataTable?.$el

        this.updateScrollContainerHeight()
        this.dataTableElement = this.$refs?.dataTable?.$refs?.tableEl

        if (!this.dataTableContainerElement) {
          return
        }

        // Bind behaviour and position of the footer scrollbar to the scroll position of the dataTableContainerElement.
        this.scrollbar.addEventListener('scroll', () => {
          this.dataTableContainerElement.scrollLeft = this.scrollbar.scrollLeft
        })

        this.dataTableContainerElement.addEventListener('scroll', () => {
          this.scrollbar.scrollLeft = this.dataTableContainerElement.scrollLeft
        })

        // Observe changes to dataTable to update scrollbar accordingly
        this.dataTableObserver = new ResizeObserver(this.updateScrollbarStyles.bind(this))

        if (this.dataTableElement) {
          this.dataTableObserver.observe(this.dataTableElement)
        }

        this.dataTableObserver.observe(this.dataTableContainerElement)

        // Set scrollbar width or conditionally hide it.
        this.updateScrollbarStyles()
      })
    })
  },
}
