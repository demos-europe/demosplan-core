<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    v-show="isVisible && hasLoaded"
    :class="prefixClass('c-map__group-item c-map__layer border border-gray-300 overflow-clip cursor-default hover:bg-transparent mb-1')"
    :title="`${Translator.trans('legend')} ${layerName}`">
    <img
      :src="legend.url"
      alt=""
      @load="markAsLoaded">
  </li>
</template>

<script>
import { mapMutations, mapState } from 'vuex'
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

  computed: {
    ...mapState('layers', [
      'apiData',
      'loadedLegends',
      'layerStates'
    ]),

    hasLoaded () {
      return this.loadedLegends.includes(this.legend.url)
    },

    isVisible () {
      const layerId = this.legend.layerId.replaceAll('-', '')

      return this.layerStates[layerId]?.isVisible || false
    },

    layerName () {
      const layer = this.apiData?.included?.find(el => el.id === this.legend.layerId)

      return layer?.attributes?.name || ''
    }
  },

  methods: {
    ...mapMutations('layers', ['markLegendAsLoaded']),

    markAsLoaded () {
      this.markLegendAsLoaded(this.legend.url)
    },

    prefixClass (classList) {
      return prefixClass(classList)
    }
  }
}
</script>
