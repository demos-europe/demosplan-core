<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <ul
    :class="prefixClass('c-map__group')"
    v-if="layers.length"
    v-show="unfolded">
    <template v-for="(layer, idx) in attributedLayers">
      <dp-public-layer-list-layer
        v-if="layer.type === 'GisLayer' && (layerType === 'overlay' || showBaseLayers)"
        :data-cy="`publicLayerListLayer:${layerType}:${idx}`"
        :key="layer.id"
        :layer="layer"
        :layer-type="layerType"
        :visible="layer.attributes.layerType === 'overlay' ? layer.attributes.hasDefaultVisibility : (layer.id === firstActiveBaseLayerId)"
        :layer-groups-alternate-visibility="layerGroupsAlternateVisibility" />
      <dp-public-layer-list-category
        v-else
        :key="`category:${layer.id}`"
        :group="layer"
        :layer-type="layerType"
        :visible="true"
        :layer-groups-alternate-visibility="layerGroupsAlternateVisibility" />
    </template>
  </ul>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import DpPublicLayerListCategory from './DpPublicLayerListCategory'
import DpPublicLayerListLayer from './DpPublicLayerListLayer'
import { prefixClass } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpPublicLayerList',

  components: {
    DpPublicLayerListCategory,
    DpPublicLayerListLayer
  },

  props: {
    layers: {
      type: Array,
      required: true,
      default: () => []
    },

    unfolded: {
      type: Boolean,
      required: true,
      default: false
    },

    layerType: {
      type: String,
      required: false,
      default: 'overlay'
    },

    layerGroupsAlternateVisibility: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'layer:toggleLayer'
  ],

  computed: {
    showBaseLayers () {
      return hasPermission('feature_participation_area_procedure_detail_map_use_baselayerbox')
    },

    attributedLayers () {
      return this.layers.map(current => {
        return this.element(current)
      })
    },

    firstActiveBaseLayerId () {
      const layers = this.layers
      const l = layers.length

      let i = 0
      let layer
      for (; i < l; i++) {
        layer = layers[i]
        if (layer.attributes.hasDefaultVisibility && layer.attributes.layerType === 'base') {
          return layer.id
        }
      }
      return ''
    },

    isMapAndLayersReady () {
      return this.layers.length > 0 && this.isMapLoaded
    },

    ...mapState('Layers', ['isMapLoaded']),
    ...mapGetters('Layers', ['element'])
  },

  watch: {
    isMapAndLayersReady: {
      handler () {
        if (this.layerType === 'base' && this.firstActiveBaseLayerId === '') {
          this.$root.$emit('layer:toggleLayer', { layerId: this.layers[0].id.replace(/-/g, ''), isVisible: true })
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    prefixClass (classList) {
      return prefixClass(classList)
    }
  },

  beforeCreate () {
    this.$options.components.dpPublicLayerListCategory = DpPublicLayerListCategory
  }
}
</script>
