<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    v-show="hasLoaded"
    :class="prefixClass('c-map__group-item c-map__layer h-auto border border-gray-300 overflow-clip cursor-default hover:bg-transparent mb-1')"
    :title="`${Translator.trans('legend')} ${layerName}`"
  >
    <img
      :class="prefixClass('max-w-full')"
      :src="legend.url"
      alt=""
      @load="markLegendAsLoaded(legend.url)"
    >
  </li>
</template>

<script>
import { mapGetters, mapMutations, mapState } from 'vuex'
import { prefixClass } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpLayerLegendItem',

  props: {
    legend: {
      required: true,
      type: Object,
    },
  },

  computed: {
    ...mapGetters('Layers', ['element']),

    ...mapState('Layers', ['loadedLegends']),

    // GetLegendGraphic fails for some services; only show images that actually loaded
    hasLoaded () {
      return this.loadedLegends.includes(this.legend.url)
    },

    layerName () {
      return this.element({ id: this.legend.layerId, type: 'GisLayer' })?.attributes?.name ?? ''
    },
  },

  methods: {
    ...mapMutations('Layers', ['markLegendAsLoaded']),

    prefixClass (classList) {
      return prefixClass(classList)
    },
  },
}
</script>
