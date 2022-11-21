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
    :id="'tag_' + segment.id">
    <div class="u-pr-0_25">
      <i
        :title="Translator.trans('tags')"
        class="fa fa-tag color--grey-light width-20 text--center" />
      <ul
        v-if="segment.tags.length > 0"
        class="o-list o-list--csv display--inline">
        <li
          v-for="(tag, idx) in segment.tags"
          :key="idx"
          class="o-list__item overflow-word-break color--grey"
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
        class="fa fa-map-marker width-20 text--center color--grey-light" />
      <span class="color--grey">
        {{ placeName }}
      </span>
    </p>

    <p
      v-if="segment.assigneeId"
      class="u-mb-0 u-pr-0_25">
      <i
        :title="Translator.trans('assigned.to')"
        class="fa fa-user width-20 text--center color--grey-light" />
      <span class="color--grey">
        {{ assigneeName }}
      </span>
    </p>

    <dp-button-icon
      icon="fa-pencil"
      :text="Translator.trans('segment.edit')"
      @click="$emit('edit-segment', segment.id)" />
    <dp-button-icon
      v-if="segment.status !== 'confirmed'"
      icon="fa-check"
      :text="Translator.trans('segment.confirm.suggestion')"
      @click="$emit('confirm-segment', segment.id)" />
    <dp-button-icon
      class="u-ml-0_25"
      icon="fa-trash"
      :text="Translator.trans('selection.tags.discard')"
      @click="$emit('delete-segment', segment.id)" />
  </div>
</template>

<script>
import { DpButtonIcon } from '@demos-europe/demosplan-ui/components/core'
import { mapGetters } from 'vuex'

export default {
  name: 'CardPaneCard',

  components: {
    DpButtonIcon
  },

  props: {
    segment: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  data () {
    return {
      offsetTop: 0,
      position: 0
    }
  },

  computed: {
    ...mapGetters('splitstatement', [
      'assignableUsers',
      'availablePlaces',
      'currentlyHighlightedSegmentId',
      'editingSegmentId',
      'editModeActive'
    ]),

    assigneeName () {
      const assignee = this.assignableUsers.find(user => user.value === this.segment.assigneeId)
      return assignee ? assignee.label : ''
    },

    isHighlighted () {
      return this.editModeActive ? this.editingSegmentId === this.segment.id : this.currentlyHighlightedSegmentId === this.segment.id
    },

    placeName () {
      const place = this.availablePlaces.find(place => place.value === this.segment.place.id)
      return place ? place.label : ''
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

      if (top < 75 && bottom > 75) {
        const parent = document.querySelector('.split-statement .container').getBoundingClientRect().top
        this.offsetTop = Math.abs(parent) + 50 + 'px'
        this.$emit('check-card-overlap')
      } else {
        this.calculateCardPosition()
      }
    })
  }
}

</script>
