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
      :class="prefixClass('c-map__group')"
    >
      <button
        :class="[unfolded ? prefixClass('is-active') : '', prefixClass('c-map__group-header c-map__group-item c-map__toggle btn--blank o-link--default u-pv-0_25')]"
        data-cy="layerLegend:legend"
        :disabled="!hasLegend"
        :title="buttonTitle"
        @click="toggle"
      >
        {{ Translator.trans('legend') }}
      </button>
    </div>

    <template v-if="hasPermission('feature_map_layer_get_legend') || hasPermission('feature_map_use_plan_draw_pdf')">
      <div
        v-show="unfolded"
        :class="prefixClass('js__mapLayerLegends')"
      >
        <!-- Plan PDF Download -->
        <template v-if="hasPermission('feature_map_use_plan_pdf') && planPdf.hash">
          <ul :class="prefixClass('c-map__group mt-2 mb-1')">
            <li>
              <a
                :class="prefixClass('c-map__group-item c-map__layer block')"
                target="_blank"
                :href="Routing.generate('core_file_procedure', { hash: planPdf.hash, procedureId: procedureId})"
                :title="planPdfTitle"
              >
                <i
                  :class="prefixClass('fa fa-download')"
                  aria-hidden="true"
                />
                {{ Translator.trans('legend.download') }}
              </a>
            </li>
          </ul>
        </template>

        <!-- Overlay Layer Legends Section -->
        <template>
          <h4
            v-show="legendImagesGroupedByLayerType.overlay.length || legendFilesGroupedByLayerType.overlay.length"
            :class="prefixClass('mt-3 mb-1 text-sm')"
          >
            {{ Translator.trans('legend.overlay_layers') }}
          </h4>

          <ul
            v-if="hasPermission('feature_map_layer_legend_file') && legendFilesGroupedByLayerType.overlay.length"
            :class="prefixClass('c-map__group mt-0 mb-1')"
          >
            <li
              v-for="layer in legendFilesGroupedByLayerType.overlay"
              :key="layer.name"
              :data-layername="layer.name"
            >
              <a
                :class="prefixClass('c-map__group-item c-map__layer block')"
                target="_blank"
                :href="Routing.generate('core_file_procedure', { hash: layer.legend.hash, procedureId: procedureId })"
                :title="`${layer.name} (${layer.legend.mimeType}, ${layer.legend.fileSize})`"
              >
                <i
                  :class="prefixClass('fa fa-download')"
                  aria-hidden="true"
                />
                {{ layer.name }} ({{ layer.legend.mimeType }}, {{ layer.legend.fileSize }})
              </a>
            </li>
          </ul>

          <ul
            v-show="legendImagesGroupedByLayerType.overlay.length"
            :class="prefixClass('c-map__group mt-0')"
          >
            <dp-layer-legend-item
              v-for="item in allVisibleLegendImagesGroupedByLayerType.overlay"
              :key="item.url"
              :legend="item"
            />
          </ul>
        </template>

        <!-- Base Layer Legends Section -->
        <template>
          <h4
            v-show="legendImagesGroupedByLayerType.base.length || legendFilesGroupedByLayerType.base.length"
            :class="prefixClass('mt-3 mb-1 text-sm')"
          >
            {{ Translator.trans('map.base') }}
          </h4>

          <ul
            v-if="hasPermission('feature_map_layer_legend_file') && legendFilesGroupedByLayerType.base.length"
            :class="prefixClass('c-map__group mt-0 mb-1')"
          >
            <li
              v-for="layer in legendFilesGroupedByLayerType.base"
              :key="layer.name"
              :data-layername="layer.name"
            >
              <a
                :class="prefixClass('c-map__group-item c-map__layer block')"
                target="_blank"
                :href="Routing.generate('core_file_procedure', { hash: layer.legend.hash, procedureId: procedureId })"
                :title="`${layer.name} (${layer.legend.mimeType}, ${layer.legend.fileSize})`"
              >
                <i
                  :class="prefixClass('fa fa-download')"
                  aria-hidden="true"
                />
                {{ layer.name }} ({{ layer.legend.mimeType }}, {{ layer.legend.fileSize }})
              </a>
            </li>
          </ul>

          <ul
            v-show="legendImagesGroupedByLayerType.base.length"
            :class="prefixClass('c-map__group mt-0')"
          >
            <dp-layer-legend-item
              v-for="item in allVisibleLegendImagesGroupedByLayerType.base"
              :key="item.url"
              :legend="item"
            />
          </ul>
        </template>
      </div>
    </template>
  </div>
</template>
<script>
import { mapGetters, mapState } from 'vuex'
import DpLayerLegendItem from './DpLayerLegendItem'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpLayerLegend',

  components: {
    DpLayerLegendItem,
  },

  mixins: [prefixClassMixin],

  props: {
    layersWithLegendFiles: {
      type: Array,
      default: () => [],
    },

    planPdf: {
      type: Object,
      default: () => ({}),
    },

    procedureId: {
      type: String,
      required: true,
    },
  },

  emits: [
    'layerLegend:unfolded',
  ],

  data () {
    return {
      unfolded: false,
    }
  },

  computed: {
    ...mapGetters('Layers', {
      legends: 'elementListForLegendSidebar',
      allVisibleLegendImagesGroupedByLayerType: 'allVisibleLegendImagesGroupedByLayerType',
      legendImagesGroupedByLayerType: 'legendImagesGroupedByLayerType',
      isLayerVisible: 'isLayerVisible',
    }),

    ...mapState('Layers', {
      apiData: 'apiData',
    }),

    visibleLayersWithLegendFiles () {
      const allLayers = this.apiData?.included?.filter(
        item => item.type === 'GisLayer'
      ) || []

      return allLayers
        .filter(layer => {
          const layerId = layer.id.replaceAll('-', '')
          return this.isLayerVisible(layerId)
        })
        .map(layer => {
          const legendFile = this.layersWithLegendFiles.find(
            file => file.name === layer.attributes.name
          )
          return legendFile ? { ...legendFile, layerType: layer.attributes.layerType } : null
        })
        .filter(legendFile => legendFile !== null)
    },

    legendFilesGroupedByLayerType () {
      const grouped = { base: [], overlay: [] }
      this.visibleLayersWithLegendFiles.forEach(legendFile => {
        const layerType = legendFile.layerType
        if (layerType === 'base' || layerType === 'overlay') {
          grouped[layerType].push(legendFile)
        }
      })
      return grouped
    },

    buttonTitle () {
      if (!this.hasLegend) {
        return Translator.trans('legend.not_available')
      }

      return ''
    },

    hasLegend () {
      return this.layersWithLegendFiles.length > 0 || this.planPdf.hash || this.legends.length > 0
    },

    planPdfTitle () {
      let fileInfo = ''
      if (this.planPdf.mimeType && this.planPdf.size) {
        fileInfo = ` (${this.planPdf.mimeType}, ${this.planPdf.size})`
      }
      return `${Translator.trans('legend.download')}${fileInfo}`
    },
  },

  methods: {
    fold () {
      this.unfolded = false
    },

    toggle () {
      const unfolded = this.unfolded = !this.unfolded
      if (unfolded) {
        this.$emit('layerLegend:unfolded')
      }
    },
  },
}
</script>
