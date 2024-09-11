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
import proj4 from 'proj4'

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
    toggle () {
      const unfolded = this.unfolded = !this.unfolded

      if (unfolded) {
        this.$root.$emit('layer-list:unfolded')
      }
    },

    prefixClass (classList) {
      return prefixClass(classList)
    }
  },

  created () {
    this.$root.$on('custom-layer:unfolded map-tools:unfolded layer-legend:unfolded', () => {
      this.unfolded = false
    })
  },

  mounted () {

    // Register the EPSG:25832 projection
    proj4.defs("EPSG:25832", "+proj=utm +zone=32 +ellps=GRS80 +units=m +no_defs");

    // Define the source CRS (EPSG:25832 for UTM zone 32N)
    const sourceCRS = 'EPSG:25832';
// Define the target CRS (WGS 84)
    const targetCRS = 'EPSG:4326';

    const minX = 1022620.5089412
    const minY = 7303548.4394861
    const maxX = 1082434.2285687
    const maxY = 7336258.1049054

// Define the BBOX coordinates
//     const minX = 529491.15106134;
//     const minY = 6072530.23884729;
//     const maxX = 530143.40083621;
//     const maxY = 6073456.86514068;

// Transform the coordinates
    const [minLon, minLat] = proj4(sourceCRS, targetCRS, [minX, minY]);
    const [maxLon, maxLat] = proj4(sourceCRS, targetCRS, [maxX, maxY]);

// Print the transformed coordinates
    console.log(`Min coordinates (longitude, latitude): (${minLon}, ${minLat})`);
    console.log(`Max coordinates (longitude, latitude): (${maxLon}, ${maxLat})`);
  }
}
</script>
