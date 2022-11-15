<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <usage variant="Without Ajax">
        <dp-height-limit
            :short="shortText"
            :full="fullText"
            no-event
            :element="element"
        ></dp-height-limit>
    </usage>

    <usage variant="With Ajax">
        <dp-height-limit
            :short="shortText"
            :full="fullText"
            no-event
            :element="element"
        ></dp-height-limit>
    </usage>
</documentation>

<template>
  <div class="overflow-word-break overflow-x-auto">
    <dp-text-wrapper :text="currentText !== '' ? currentText : ' '" />

    <button
      class="btn--blank o-link--default"
      type="button"
      :aria-label="Translator.trans('aria.toggle')"
      @click.stop="toggle"
      v-if="isShortened">
      {{ Translator.trans(isExpanded ? 'show.less' : 'show.more') }}
    </button>
  </div>
</template>

<script>
import DpTextWrapper from './DpTextWrapper'

export default {
  name: 'DpHeightLimit',

  components: {
    DpTextWrapper
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
