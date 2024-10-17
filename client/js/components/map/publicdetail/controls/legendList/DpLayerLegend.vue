<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div
      v-if="hasPermission('feature_map_layer_legend_file') || hasPermission('feature_map_use_plan_draw_pdf')"
      :class="prefixClass('c-map__group')">
      <button
        :class="[unfolded ? prefixClass('is-active') : '', prefixClass('c-map__group-header c-map__group-item c-map__toggle btn--blank o-link--default u-pv-0_25')]"
        data-cy="layerLegend:legend"
        @click="toggle">
        {{ Translator.trans('legend') }}
      </button>
    </div>

    <template v-if="hasPermission('feature_map_layer_get_legend') || hasPermission('feature_map_use_plan_draw_pdf')">
      <ul
        :class="prefixClass('c-map__group js__mapLayerLegends')"
        v-show="unfolded">
        <li v-if="hasPermission('feature_map_use_plan_pdf') && planPdf.hash">
          <a
            :class="prefixClass('c-map__group-item block')"
            target="_blank"
            :href="Routing.generate('core_file_procedure', { hash: planPdf.hash, procedureId: procedureId})"
            :title="planPdfTitle">
            <i
              :class="prefixClass('fa fa-download')"
              aria-hidden="true" />
            {{ Translator.trans('legend.download') }}
          </a>
        </li>

        <dp-layer-legend-item
          v-for="item in legends"
          :key="item.id"
          :legend="item" />

        <template v-if="hasPermission('feature_map_layer_legend_file')">
          <li
            v-for="(layer, idx) in layersWithLegendFiles"
            :key="idx"
            :data-layername="layer.name">
            <a
              :class="prefixClass('c-map__group-item block')"
              target="_blank"
              :href="Routing.generate('core_file_procedure', { hash: layer.legend.hash, procedureId: procedureId })"
              :title="`${layer.name} (${layer.legend.mimeType}, ${layer.legend.fileSize})`">
              {{ layer.name }}
            </a>
          </li>
        </template>
      </ul>
    </template>
  </div>
</template>
<script>
import DpLayerLegendItem from './DpLayerLegendItem'
import { mapGetters } from 'vuex'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpLayerLegend',

  components: {
    DpLayerLegendItem
  },

  mixins: [prefixClassMixin],

  props: {
    layersWithLegendFiles: {
      type: Array,
      default: () => []
    },

    planPdf: {
      type: Object,
      default: () => ({})
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  emits: [
    'layer-legend:unfolded'
  ],

  data () {
    return {
      unfolded: false
    }
  },

  computed: {
    ...mapGetters('Layers', {
      legends: 'elementListForLegendSidebar'
    }),

    planPdfTitle () {
      let fileInfo = ''
      if (this.planPdf.mimeType && this.planPdf.size) {
        fileInfo = ` (${this.planPdf.mimeType}, ${this.planPdf.size})`
      }
      return `${Translator.trans('legend.download')}${fileInfo}`
    }
  },

  methods: {
    toggle () {
      const unfolded = this.unfolded = !this.unfolded
      if (unfolded) {
        this.$root.$emit('layer-legend:unfolded')
      }
    }
  },

  created () {
    this.$root.$on('layer-list:unfolded map-tools:unfolded custom-layer:unfolded', () => { this.unfolded = false })
  }
}
</script>
