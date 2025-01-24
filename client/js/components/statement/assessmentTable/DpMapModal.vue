<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <portal to="vueModals">
    <dp-modal
      ref="mapModal"
      content-classes="u-1-of-2 u-pb"
      @modal:toggled="(open) => {isModalOpen = open}">
      <dp-ol-map
        v-if="isModalOpen"
        ref="map"
        :class="prefixClass('u-mv-0_5')"
        map-id="mapModal"
        :map-options-route="mapOptionsRoute"
        :options="{
          autoSuggest: false,
          scaleSelect: false,
          procedureExtent: true
        }"
        :procedure-id="procedureId">
        <dp-ol-map-layer-vector
          zoom-to-drawing
          :features="drawing" />
      </dp-ol-map>
    </dp-modal>
  </portal>
</template>

<script>
import { DpModal, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'DpMapModal',

  components: {
    DpModal,
    DpOlMap: defineAsyncComponent(() => import('@DpJs/components/map/map/DpOlMap')),
    DpOlMapLayerVector: defineAsyncComponent(() => import('@DpJs/components/map/map/DpOlMapLayerVector'))
  },

  mixins: [prefixClassMixin],

  props: {
    procedureId: {
      required: false,
      type: String,
      default: ''
    },

    mapOptionsRoute: {
      required: false,
      type: String,
      default: 'dplan_api_map_options_public'
    }
  },

  data () {
    return {
      isLoading: true,
      drawingData: {},
      isModalOpen: false
    }
  },

  computed: {
    drawing () {
      if (JSON.stringify(this.drawingData) !== JSON.stringify({})) {
        return this.drawingData
      } else {
        return {}
      }
    }
  },

  methods: {
    toggleModal (drawingData) {
      this.drawingData = drawingData
      this.$refs.mapModal.toggle()
    }
  },

  mounted () {
    this.$root.$on('toggleMapModal', (drawingData) => {
      this.toggleModal(drawingData)
    })
  }
}
</script>
