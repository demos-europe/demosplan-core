<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div :class="prefixClass('c-map__group u-mt-0_5')">
      <button
        data-cy="publicLayerListWrapper:mapLayerShowHide"
        @click="toggle"
        :class="[dimmed ? prefixClass('color--grey'): '', unfolded ? prefixClass('is-active'): '', prefixClass('btn--blank o-link--default u-pv-0_25 c-map__group-header c-map__group-item u-m-0')]">
        {{ Translator.trans('maplayer.show/hide') }}
      </button>
    </div>
    <dp-public-layer-list
      :layer-groups-alternate-visibility="layerGroupsAlternateVisibility"
      :layers="overlayLayers"
      :unfolded="unfolded"
      layer-type="overlay" />

    <div
      v-if="baseLayers.length > 0 && unfolded && showBaseLayers"
      :class="prefixClass('c-map__group')">
      <div :class="prefixClass('c-map__layer pointer-events-none bg-color--grey-light-1')">
        {{ Translator.trans('map.bases') }}
      </div>
    </div>
    <dp-public-layer-list
      :layers="baseLayers"
      :unfolded="unfolded"
      layer-type="base" />
  </div>
</template>

<script>
import DpPublicLayerList from './DpPublicLayerList'
import { prefixClass } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpPublicLayerListWrapper',

  components: {
    DpPublicLayerList
  },

  props: {
    layerGroupsAlternateVisibility: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'layer-list:unfolded'
  ],

  data () {
    return {
      unfolded: false
    }
  },

  computed: {
    showBaseLayers () {
      return hasPermission('feature_participation_area_procedure_detail_map_use_baselayerbox')
    },

    overlayLayers () {
      return this.$store.getters['Layers/elementListForLayerSidebar'](null, 'overlay', true)
    },

    baseLayers () {
      return this.$store.getters['Layers/elementListForLayerSidebar'](null, 'base', false)
    },

    dimmed () {
      return (this.overlayLayers.length + this.baseLayers.length) <= 0
    }
  },

  methods: {
    fold () {
      this.unfolded = false
    },

    toggle () {
      const unfolded = this.unfolded = !this.unfolded

      if (unfolded) {
        this.$emit('layer-list:unfolded')
      }
    },

    prefixClass (classList) {
      return prefixClass(classList)
    }
  }
}
</script>
