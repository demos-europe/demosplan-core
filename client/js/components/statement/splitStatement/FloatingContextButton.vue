<template>
  <button
    v-show="isVisible"
    class="bg-white rounded shadow absolute p-0.5"
    :aria-controls="section"
    :aria-expanded="isContentCollapsed"
    :data-cy="`sidebar:floatingContextButton:${section}`"
    @click="toggleContentVisibility"
    @mouseover="show"
    @mouseleave="hide">
    <dp-icon
      :aria-label="Translator.trans('content.show/hide')"
      class="w-4 h-4 rounded-sm text-interactive hover:text-interactive-hover active:text-interactive-active hover:bg-interactive-subtle-hover active:bg-interactive-subtle-active"
      :icon="isContentCollapsed ? 'chevron-up' : 'chevron-down'"
      size="medium" />
  </button>
</template>

<script>
import { DpIcon } from '@demos-europe/demosplan-ui'

export default {
  name: 'FloatingContextButton',

  components: {
    DpIcon
  },

  props: {
    isContentCollapsed: {
      type: Boolean,
      required: true
    },

    isVisible: {
      type: Boolean,
      required: true
    },

    section: {
      type: String,
      required: true
    }
  },

  emits: [
    'hide',
    'show',
    'toggle-content-visibility'
  ],

  methods: {
    toggleContentVisibility () {
      this.$emit('toggle-content-visibility', this.section)
    },

    show () {
      this.$emit('show')
    },

    hide () {
      this.$emit('hide')
    }
  }
}
</script>
