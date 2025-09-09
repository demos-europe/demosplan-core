<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="layout--flush u-ph-0_5"
    :class="{
      'border--bottom': borderBottom,
      'border--right': borderRight,
      'fullscreen': isFullscreen,
      'fullscreen-row': isFullscreenRow
    }">
    <div
      v-if="icon"
      class="layout__item u-pl-0 c-at-item__row-icon color--grey"
      :title="Translator.trans(title)">
      <i
        aria-hidden="true"
        class="fa"
        :class="icon" />
    </div>
    <button
      v-if="isFullscreenRow"
      :aria-label="Translator.trans('fullscreen')"
      class="btn--blank absolute right-1 top-1 z-above-zero"
      data-cy="rowFullscreen"
      v-tooltip="Translator.trans('fullscreen')"
      @click.stop.prevent="toggleFullscreen">
      <dp-icon
        aria-hidden="true"
        class="inline-block"
        :icon="isFullscreen ? 'compress' : 'expand'" />
    </button>
    <div class="layout--flush layout__item c-at-item__row relative">
      <slot />
    </div>
  </div>
</template>

<script>
import { DpIcon } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpItemRow',

  components: {
    DpIcon
  },

  props: {
    icon: {
      required: false,
      type: String,
      default: ''
    },

    title: {
      required: false,
      type: String,
      default: ''
    },

    borderBottom: {
      required: false,
      type: Boolean,
      default: true
    },

    borderRight: {
      required: false,
      type: Boolean,
      default: false
    },

    isFullscreenRow: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      isFullscreen: false
    }
  },

  methods: {
    toggleFullscreen () {
      this.isFullscreen = !this.isFullscreen

      if (this.isFullscreen) {
        document.querySelector('html').style = 'overflow: hidden;'
      } else {
        document.querySelector('html').style = ''
      }
    }
  }
}
</script>
