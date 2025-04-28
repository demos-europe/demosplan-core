<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="card-pane"
    :style="{ 'min-height': containerMinHeight }">
    <card-pane-card
      v-for="segment in filteredSortedSegments"
      :key="'card' + segment.id + Math.random()"
      :segment="segment"
      :data-range="segment.id"
      :offset="offset"
      ref="card"
      @card:checkOverlap="positionCards"
      @segment:confirm="$emit('segment:confirm', segment.id)"
      @segment:edit="$emit('segment:edit', segment.id)"
      @segment:delete="$emit('segment:delete', segment.id)"
      @focusin="handleMouseEnter(segment.id)"
      @focusout="handleMouseLeave(segment.id)"
      @mouseenter="handleMouseEnter(segment.id)"
      @mouseleave="handleMouseLeave(segment.id)" />
  </div>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import CardPaneCard from './CardPaneCard'

export default {
  name: 'CardPane',

  components: {
    CardPaneCard
  },

  props: {
    /**
     * To calculate the max-height of the sideBar, the offset from top is needed - which may differ
     * depending on how much space is occupied by the header.
     */
    offset: {
      type: Number,
      required: true
    },

    maxRange: {
      required: true,
      type: Number
    }
  },

  emits: [
    'segment:delete',
    'segment:edit',
    'segment:confirm'
  ],

  data () {
    return {
      containerMinHeight: ''
    }
  },

  computed: {
    ...mapGetters('SplitStatement', [
      'currentlyHighlightedSegmentId',
      'sortedSegments'
    ]),

    filteredSortedSegments () {
      return this.sortedSegments.filter(el => el.charEnd <= this.maxRange)
    }
  },

  methods: {
    ...mapMutations('SplitStatement', [
      'setProperty'
    ]),

    handleCardHighlighting (segmentId, highlight) {
      const card = document.querySelector(`div[data-range="${segmentId}"]`)
      if (card) {
        if (highlight) {
          card.classList.add('highlighted')
        } else {
          card.classList.remove('highlighted')
        }
      }
    },

    handleMouseEnter (segmentId) {
      this.handleSegmentHighlighting(segmentId, true)
      this.handleCardHighlighting(segmentId, true)
    },

    handleMouseLeave (segmentId) {
      this.handleSegmentHighlighting(segmentId, false)
      this.handleCardHighlighting(segmentId, false)
    },

    handleSegmentHighlighting (segmentId, highlight = false) {
      const id = segmentId || this.currentlyHighlightedSegmentId
      const highlightedSegmentId = highlight ? segmentId : null
      const segmentParts = Array.from(document.querySelectorAll(`span[data-range="${id}"]`))

      segmentParts.forEach(part => {
        if (highlight && !part.classList.contains('highlighted')) {
          part.classList.add('highlighted')
        }
        if (!highlight && part.classList.contains('highlighted')) {
          part.classList.remove('highlighted')
        }
      })

      this.setProperty({ prop: 'currentlyHighlightedSegmentId', val: highlightedSegmentId })
    },

    positionCards () {
      // Check offset of other cards. if it is same, position it left-right
      const groupedCards = {}
      if (typeof this.$refs.card === 'undefined') {
        return false
      }

      this.$refs.card.forEach((card) => {
        const style = card.offsetTop
        if (groupedCards[style]) {
          groupedCards[style].push(card)
        } else {
          groupedCards[style] = [card]
        }
      })

      Object.values(groupedCards).forEach(group => {
        group.sort((a, b) => a.segment.charStart - b.segment.charStart)
        group.forEach((el, idx) => {
          el.position = idx + 1
        })
      })

      this.$nextTick(this.setContainerHeight)
    },

    /**
     * If a tag is applied to the last line of the text, the corresponding CardPaneCard
     * will be overlapped by the "Aufteilen abschließen" button in a way that makes it
     * unreadable. To prevent this, a min-height is set on the container to force the
     * button to be nicely spaced when scrolling to the bottom of the page.
     * This function must be called after positionCards() has run - otherwise the positions
     * of the cards are not set correctly.
     */
    setContainerHeight () {
      const cardBottomValues = this.$refs.card.map((card) => card.$el.getBoundingClientRect().bottom)
      const maxBottom = Math.max(...cardBottomValues)
      const containerBounds = this.getContainerBounds()
      this.containerMinHeight = `${maxBottom + containerBounds.height - containerBounds.bottom}px`
    },

    getContainerBounds () {
      const offset = window.pageYOffset || document.documentElement.scrollTop

      return {
        bottom: -offset + this.containerSize.top + this.containerSize.height,
        height: this.containerSize.height,
        top: -offset + this.containerSize.top
      }
    }
  },

  mounted () {
    this.containerSize = this.$el.getBoundingClientRect()
    this.positionCards()

    document.addEventListener('resize', this.positionCards)
  },

  unmounted () {
    document.removeEventListener('resize', this.positionCards)
  }
}
</script>
