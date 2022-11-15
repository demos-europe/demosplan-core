<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>

<!--  can toggle itself via the toggle method, but can also be toggled from outside (e.g. via a save or abort button)
      by passing the parent's open state as prop (isOpen) and calling a toggle method on the parent @item:toggle which
      toggles the parent's isOpen data property, see usage -->

  <usage variant="with toggle from outside">
    <dp-accordion
      :is-open="isOpen"
      :title="title"
      @item:toggle="(open) => toggleItem(open)">
    </dp-accordion>
  </usage>
</documentation>

<template>
  <div class="o-accordion">
    <button
      type="button"
      v-if="title !== ''"
      @click="() => toggle()"
      :aria-expanded="isVisible.toString()"
      data-cy="accordionToggle"
      :class="fontWeight === 'bold' ? 'weight--bold' : 'weight--normal'"
      class="btn--blank o-link--default">
      <i
        class="width-s fa"
        :class="{'fa-caret-right': !isVisible, 'fa-caret-down': isVisible}"
        aria-hidden="true" />
      <span :class="compressed ? 'font-size-medium' : 'o-accordion--title'">{{ title }}</span>
    </button>
    <dp-transition-expand>
      <div v-show="isVisible">
        <!-- This is where the accordion content goes. -->
        <slot />
      </div>
    </dp-transition-expand>
  </div>
</template>

<script>
import DpTransitionExpand from './DpTransitionExpand'

export default {
  name: 'DpAccordion',

  components: {
    DpTransitionExpand
  },

  props: {
    fontWeight: {
      type: String,
      required: false,
      default: 'bold',
      validate: prop => ['normal', 'bold'].includes(prop)
    },

    // Reduce font-size of the title
    compressed: {
      type: Boolean,
      default: false
    },

    // Needed if you want to toggle the accordion from outside
    isOpen: {
      type: Boolean,
      required: false,
      default: false
    },

    // Text displayed in toggle trigger
    title: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      isVisible: this.isOpen
    }
  },

  watch: {
    isOpen () {
      this.isVisible = this.isOpen
    }
  },

  methods: {
    toggle (state) {
      this.isVisible = (typeof state === 'undefined') ? !this.isVisible : state
      this.$emit('item:toggle', this.isVisible)
    }
  }
}
</script>
