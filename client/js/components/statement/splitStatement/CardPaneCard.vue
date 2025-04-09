<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="card space-stack-xs"
    :class="{ 'highlighted': isHighlighted }"
    :style="{ top: offsetTop, left: (position * 5) + 'px', 'margin-top': (position * 5) + 'px' }"
    :id="'tag_' + segment.id"
    @focusin="$emit('focusin')"
    @focusout="$emit('focusout')"
    @mouseenter="$emit('mouseenter')"
    @mouseleave="$emit('mouseleave')">
    <div class="u-pr-0_25">
      <i
        :title="Translator.trans('tags')"
        class="fa fa-tag color--grey-light w-3 text-center" />
      <ul
        v-if="segment.tags.length > 0"
        class="o-list o-list--csv inline">
        <li
          v-for="(tag, idx) in segment.tags"
          :key="idx"
          class="o-list__item break-words color--grey"
          v-text="tag.tagName" />
      </ul>
      <template v-else>
        ---
      </template>
    </div>

    <p
      v-if="segment.place.id"
      class="u-mb-0 u-pr-0_25">
      <i
        :title="Translator.trans('workflow.place')"
        class="fa fa-map-marker w-3 text-center color--grey-light" />
      <span class="color--grey">
        {{ placeName }}
      </span>
    </p>

    <p
      v-if="segment.assigneeId"
      class="u-mb-0 u-pr-0_25">
      <i
        :title="Translator.trans('assigned.to')"
        class="fa fa-user w-3 text-center color--grey-light" />
      <span class="color--grey">
        {{ assigneeName }}
      </span>
    </p>

    <dp-button-icon
      icon="fa-pencil"
      :text="Translator.trans('segment.edit')"
      @click="$emit('edit-segment', segment.id)" />
    <addon-wrapper
      :addon-props="{
        class: 'mt-1',
        segmentStatus: segment.status ? segment.status : 'not confirmed'
      }"
      class="inline-block"
      hook-name="split.statement.buttons"
      @segment:confirm="$emit('segment:confirm', segment.id)" />
    <dp-button-icon
      icon="fa-trash"
      :text="Translator.trans('selection.tags.discard')"
      @click="$emit('delete-segment', segment.id)" />
  </div>
</template>

<script>
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import { DpButtonIcon } from '@demos-europe/demosplan-ui'
import { mapGetters } from 'vuex'

export default {
  name: 'CardPaneCard',

  components: {
    AddonWrapper,
    DpButtonIcon
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

    segment: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  emits: [
    'check-card-overlap',
    'delete-segment',
    'edit-segment',
    'focusin',
    'focusout',
    'mouseenter',
    'mouseleave',
    'segment:confirm'
  ],

  data () {
    return {
      offsetTop: 0,
      position: 0
    }
  },

  computed: {
    ...mapGetters('SplitStatement', [
      'assignableUsers',
      'availablePlaces',
      'currentlyHighlightedSegmentId',
      'editingSegmentId',
      'editModeActive'
    ]),

    assigneeName () {
      const assignee = this.assignableUsers.find(user => user.id === this.segment.assigneeId)

      return assignee ? assignee.name : ''
    },

    isHighlighted () {
      return this.editModeActive ? this.editingSegmentId === this.segment.id : this.currentlyHighlightedSegmentId === this.segment.id
    },

    placeName () {
      const place = this.availablePlaces.find(place => place.id === this.segment.place.id)

      return place ? place.name : ''
    }
  },

  methods: {
    calculateCardPosition () {
      const segmentEl = document.querySelector(`#editor [data-range="${this.segment.id}"]`)
      if (segmentEl) {
        const mainOffset = document.querySelector('div.ProseMirror').getBoundingClientRect().top
        const segmentOffset = segmentEl.getBoundingClientRect().top
        this.offsetTop = segmentOffset - mainOffset + 'px'
      }

      this.$emit('check-card-overlap')
    }
  },

  mounted () {
    this.calculateCardPosition()
    window.addEventListener('resize', () => {
      this.$nextTick(() => this.calculateCardPosition())
    })

    window.addEventListener('scroll', () => {
      const segmentSpans = Array.from(document.querySelectorAll(`#editor [data-range="${this.segment.id}"]`))
      if (segmentSpans.length === 0) {
        return
      }

      const firstSpan = segmentSpans[0]
      const lastSpan = segmentSpans[segmentSpans.length - 1]

      const top = firstSpan.getBoundingClientRect().top
      const bottom = lastSpan.getBoundingClientRect().bottom
      const threshold = this.offset + 5

      if (top < threshold && bottom > threshold) {
        const parent = document.querySelector('.split-statement .container').getBoundingClientRect().top
        /*
         * The magic 24 is subtracted to compensate the padding-top
         * between segmentation-editor container and fixed header.
         */
        this.offsetTop = Math.abs(parent) + this.offset - 24 + 'px'
        this.$emit('check-card-overlap')
      } else {
        this.calculateCardPosition()
      }
    })
  }
}

</script>
