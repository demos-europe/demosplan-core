<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    v-if="!isBroken"
    v-show="isVisible"
    class="h-auto"
    :class="prefixClass('c-map__group-item c-map__layer')">
    <img
      :src="legend.url"
      alt=""
      @error="deleteImage">
  </li>
</template>

<script>
import { prefixClass } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpLayerLegendItem',

  props: {
    legend: {
      required: true,
      type: Object,
      default: () => { return { layerId: '', url: '#', defaultVisibility: false } }
    }
  },

  data () {
    return {
      isVisible: this.legend.defaultVisibility,
      isBroken: false
    }
  },

  methods: {
    toggle (layerId, visibilityState) {
      if (this.legend.layerId.replace(/-/g, '') === layerId) {
        this.isVisible = visibilityState
      }
    },

    prefixClass (classList) {
      return prefixClass(classList)
    },

    deleteImage () {
      this.isBroken = true
    }
  },

  created () {
    this.$root.$on('layer:toggleLegend', ({ id, isVisible }) => {
      this.toggle(id, isVisible)
    })
  }
}
</script>
