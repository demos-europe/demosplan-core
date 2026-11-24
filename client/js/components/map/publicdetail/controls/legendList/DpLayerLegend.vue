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

    <div
      v-if="hasPermission('feature_map_layer_get_legend') || hasPermission('feature_map_use_plan_draw_pdf')"
      v-show="unfolded"
    >
      <ul
        v-if="hasPermission('feature_map_use_plan_pdf') && planPdf.hash"
        :class="prefixClass('c-map__group mt-2 mb-1')"
      >
        <li>
          <a
            :class="prefixClass('c-map__group-item c-map__layer block h-auto')"
            target="_blank"
            :href="Routing.generate('core_file_procedure', { hash: planPdf.hash, procedureId: procedureId})"
            :title="planPdfTitle"
          >
            <i
              :class="prefixClass('fa fa-download')"
              aria-hidden="true"
            />
            {{ Translator.trans('drawing.explanation.download') }}
          </a>
        </li>
      </ul>

      <section
        v-for="section in legendSections"
        :key="section.layerType"
        :data-cy="`layerLegend:${section.layerType}`"
      >
        <h4
          v-show="section.files.length > 0 || section.hasLoadedImages"
          :class="prefixClass('mt-3 mb-1 text-sm')"
        >
          {{ section.title }}
        </h4>

        <ul
          v-if="section.files.length > 0"
          :class="prefixClass('c-map__group mt-0 mb-1')"
        >
          <li
            v-for="file in section.files"
            :key="file.id"
          >
            <a
              :class="prefixClass('c-map__group-item c-map__layer block h-auto')"
              target="_blank"
              :href="Routing.generate('core_file_procedure', { hash: file.legend.hash, procedureId: procedureId })"
              :title="`${file.name} (${file.legend.mimeType}, ${file.legend.fileSize})`"
            >
              <i
                :class="prefixClass('fa fa-download')"
                aria-hidden="true"
              />
              {{ file.name }} ({{ file.legend.mimeType }}, {{ file.legend.fileSize }})
            </a>
          </li>
        </ul>

        <ul
          v-show="section.hasLoadedImages"
          :class="prefixClass('c-map__group mt-0')"
        >
          <dp-layer-legend-item
            v-for="legend in section.images"
            :key="legend.url"
            :legend="legend"
          />
        </ul>
      </section>

      <p
        v-if="!hasVisibleLayerLegend"
        :class="prefixClass('mt-2 mb-1 text-sm')"
        data-cy="layerLegend:empty"
      >
        {{ Translator.trans('legend.none_for_visible_layers') }}
      </p>
    </div>
  </div>
</template>
<script>
import { mapGetters, mapState } from 'vuex'
import DpLayerLegendItem from './DpLayerLegendItem.vue'
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
      element: 'element',
      isLayerVisible: 'isLayerVisible',
      legends: 'elementListForLegendSidebar',
      visibleLegendsByLayerType: 'visibleLegendsByLayerType',
    }),

    ...mapState('Layers', ['loadedLegends']),

    buttonTitle () {
      if (!this.hasLegend) {
        return Translator.trans('legend.not_available')
      }

      return ''
    },

    // Counts legends of all layers so the button stays usable while layers are toggled
    hasLegend () {
      return this.layersWithLegendFiles.length > 0 || this.planPdf.hash || this.legends.length > 0
    },

    hasVisibleLayerLegend () {
      return this.legendSections.some(section => section.files.length > 0 || section.hasLoadedImages)
    },

    legendSections () {
      return [
        { layerType: 'overlay', title: Translator.trans('legend.overlay_layers') },
        { layerType: 'base', title: Translator.trans('map.base') },
      ].map(section => {
        const images = this.visibleLegendsByLayerType[section.layerType]

        return {
          ...section,
          files: this.visibleLegendFiles.filter(file => this.layerType(file.id) === section.layerType),
          hasLoadedImages: images.some(legend => this.loadedLegends.includes(legend.url)),
          images,
        }
      })
    },

    planPdfTitle () {
      let fileInfo = ''

      if (this.planPdf.mimeType && this.planPdf.size) {
        fileInfo = ` (${this.planPdf.mimeType}, ${this.planPdf.size})`
      }

      return `${Translator.trans('drawing.explanation.download')}${fileInfo}`
    },

    visibleLegendFiles () {
      if (!hasPermission('feature_map_layer_legend_file')) {
        return []
      }

      return this.layersWithLegendFiles.filter(file => this.isLayerVisible(file.id))
    },
  },

  methods: {
    fold () {
      this.unfolded = false
    },

    layerType (layerId) {
      return this.element({ id: layerId, type: 'GisLayer' })?.attributes?.layerType
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
