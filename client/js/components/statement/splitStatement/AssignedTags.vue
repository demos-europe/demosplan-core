<template>
  <div
    v-if="segment && segment.tags.length > 0"
    class="flex flex-wrap gap-1 bg-white">
    <div
      v-for="(tag, idx) in segment.tags"
      :key="`tag_${idx}`"
      :class="assignTagSizeClasses(tag,idx)">
      <div
        :class="[
          'tag flex whitespace-nowrap overflow-hidden text-sm px-0.5 py-0.5',
          isTagAppliedToSegment(tag.id) ? 'bg-status-neutral': 'bg-status-complete',
          isLastTagWithEvenPosition(idx) ? 'w-fit' : ''
        ]"
        v-tooltip="tag.tagName">
        <span class="overflow-hidden text-ellipsis">
          {{ tag.tagName }}
        </span>
        <button
          type="button"
          class="tag__remove btn--blank o-link--default ml-1"
          data-cy="sidebar:removeTag"
          @click="removeTag(tag.id)">
          <dp-icon
            icon="close"
            size="small" />
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { DpIcon } from '@demos-europe/demosplan-ui'

export default {
  name: 'AssignedTags',

  components: {
    DpIcon
  },

  props: {
    availableTags: {
      type: Array,
      required: true
    },

    currentSegment: {
      type: Object,
      required: true
    },

    initialSegments: {
      type: Array,
      required: true
    },

    segment: {
      type: Object,
      required: true
    }
  },

  methods: {
    assignTagSizeClasses (tag, idx) {
      const classes = ['flex']

      if (this.isTagNameLongerThanLimit(tag)) {
        classes.push('w-[calc(50%-4px)]')

        if (this.isLastTagWithEvenPosition(idx)) {
          classes.push('flex-1')
        }

        const isNextTagShort = !this.isTagNameLongerThanLimit(this.segment.tags[idx + 1])
        if (isNextTagShort || this.isEven(idx + 1)) {
          classes.push('flex-1')
        }
      }

      return classes
    },

    isEven (number) {
      return number % 2 === 0
    },

    isLastTagWithEvenPosition (idx) {
      return idx === this.segment.tags.length - 1 && this.isEven(idx)
    },

    isTagNameLongerThanLimit (tag) {
      if (tag) {
        return tag.tagName.length > 14
      }
    },

    isTagAppliedToSegment (tagId) {
      if (this.initialSegments.length > 0) {
        const segment = this.initialSegments.find(seg => seg.id === this.currentSegment.id)

        if (segment) {
          return segment.tags.some(tag => tag.id === tagId)
        }
      }
    },

    removeTag (id) {
      const tagToBeDeleted = this.availableTags.find(tag => tag.id === id)
      this.$emit('remove', { id, tagName: tagToBeDeleted.attributes.title })
    }
  }
}
</script>
