<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <ul
    v-if="layers.length"
    v-show="unfolded"
    :class="prefixClass('c-map__group')"
  >
    <template v-for="(layer, idx) in attributedLayers">
      <dp-public-layer-list-layer
        v-if="layer.type === 'GisLayer' && (layerType === 'overlay' || showBaseLayers)"
        :key="layer.id"
        :data-cy="`publicLayerListLayer:${layerType}:${idx}`"
        :layer="layer"
        :layer-groups-alternate-visibility="layerGroupsAlternateVisibility"
        :layer-type="layerType"
        :parent-is-visible="parentIsVisible"
        :visible="layer.attributes.layerType === 'overlay' ? layer.attributes.hasDefaultVisibility : (layer.id === firstActiveBaseLayerId)"
      />
      <dp-public-layer-list-category
        v-else
        :key="`category:${layer.id}`"
        :group="layer"
        :layer-groups-alternate-visibility="layerGroupsAlternateVisibility"
        :layer-type="layerType"
        :parent-is-visible="parentIsVisible"
        :visible="true"
      />
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
    DpPublicLayerListLayer,
  },

  props: {
    layers: {
      type: Array,
      required: true,
      default: () => [],
    },

    unfolded: {
      type: Boolean,
      required: true,
      default: false,
    },

    layerType: {
      type: String,
      required: false,
      default: 'overlay',
    },

    layerGroupsAlternateVisibility: {
      type: Boolean,
      required: false,
      default: false,
    },

    parentIsVisible: {
      type: Boolean,
      required: false,
      default: true,
    },
  },

  data () {
    return {
      isLoading: true,
    }
  },

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
    ...mapGetters('Layers', ['element']),
  },

  watch: {
    isMapAndLayersReady: {
      handler () {
        if (!this.isLoading) return

        this.isLoading = false
        this.$store.commit('Layers/setInitialLayerState')

        this.$nextTick(() => {
          const firstActiveBaseLayerId = this.getFirstActiveBaseLayerId()

          if (!firstActiveBaseLayerId) return

          this.$store.dispatch('Layers/toggleBaselayer', { id: firstActiveBaseLayerId, setToVisible: true })
        })
      },
      deep: false, // Set default for migrating purpose. To know this occurrence is checked
    },
  },

  methods: {
    /**
     * Get the first active base layer ID from the list of layers.
     *
     * @return {string}
     */
    getFirstActiveBaseLayerId () {
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

      return this.layers.filter(layer => layer.attributes.layerType === 'base')[0]?.id || ''
    },

    /**
     * Returns a prefixed class name for the given classList.
     *
     * @param {Array} classList - List of class names to prefix.
     * @returns {Array} - Prefixed class names.
     */
    prefixClass (classList) {
      return prefixClass(classList)
    },
  },

  beforeCreate () {
    this.$options.components.dpPublicLayerListCategory = DpPublicLayerListCategory
  },
}
</script>
