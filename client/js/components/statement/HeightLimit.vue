<template>
  <div class="break-words overflow-x-auto">
    <text-content-renderer :text="currentText !== '' ? currentText : ' '" />

    <button
      class="btn--blank o-link--default"
      :data-cy="isExpanded ? 'showLessText' : 'showMoreText'"
      type="button"
      :aria-label="Translator.trans('aria.toggle')"
      @click.stop="toggle"
      v-if="isShortened">
      {{ Translator.trans(isExpanded ? 'show.less' : 'show.more') }}
    </button>
  </div>
</template>

<script>
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'

export default {
  name: 'HeightLimit',

  components: {
    TextContentRenderer
  },

  props: {
    /**
     * The short text
     */
    shortText: {
      type: String,
      required: true
    },

    /**
     * The long text
     */
    fullText: {
      type: String,
      required: true
    },

    /**
     * If this is true, no callback-event will be fired for full text loading
     */
    noEvent: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Translation string for element to be height-toggled
     */
    element: {
      type: String,
      required: true
    },

    isShortened: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'heightLimit:toggle'
  ],

  data () {
    return {
      isExpanded: false
    }
  },

  computed: {
    currentText () {
      return (this.isExpanded) ? this.fullText : this.shortText
    }
  },

  methods: {
    toggle () {
      if (this.noEvent) {
        this.isExpanded = !this.isExpanded
        return
      }

      this.$emit('heightLimit:toggle', () => {
        this.isExpanded = !this.isExpanded
      })
    }
  }
}
</script>
