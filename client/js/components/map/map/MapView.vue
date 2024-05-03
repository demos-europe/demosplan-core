<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!--
        This component lets a user chose a bounding box and initial mapExtent for the public detail map.
        On mounted, if procedure already has coordinates, the point with the coordinates is added to the map.
     -->
</documentation>

<template>
  <div>
    <dp-ol-map
      ref="map"
      :map-options="{
        procedureMaxExtent: maxExtent,
        procedureExtent: true,
        initialExtent: true,
        initCenter: center,
        scales: scales.map(s => s.value)
      }"
      :options="{
        autoSuggest: false,
        defaultAttribution: defaultAttribution
      }"
      :procedure-id="procedureId">
      <template v-slot:controls>
        <div class="border--bottom u-pv-0_5 flow-root">
          <i
            aria-hidden="true"
            class="fa fa-map u-ml-0_25 color--grey-light" />
          <dp-ol-map-layer-vector
            v-if="boundingBox"
            class="u-mb-0_5"
            :features="boundingBox"
            name="mapSettingsPreviewInitExtent"
            zoom-to-drawing />

          <dp-ol-map-layer-vector
            v-if="mapExtent"
            class="u-mb-0_5"
            :draw-style="drawingStyles.mapExtent"
            :features="mapExtent"
            name="mapSettingsPreviewMapExtent" />
          <dp-ol-map-set-extent
            data-cy="mapDefaultBounds"
            translation-key="map.default.bounds"
            @extentSet="data => setExtent({ field: 'boundingBox', extent: data })" />
          <dp-ol-map-set-extent
            data-cy="boundsApply"
            translation-key="bounds.apply"
            @extentSet="data => setExtent({ field: 'mapExtent', extent: data })" />
          <dp-contextual-help
            class="float-right"
            :text="Translator.trans('text.mapsection')" />
        </div>

        <div
          v-if="hasPermission('feature_map_use_territory')"
          class="border--bottom u-pv-0_5 flow-root">
          <i
            aria-hidden="true"
            class="fa fa-pencil u-ml-0_25 color--grey-light" />
          <dp-ol-map-draw-feature
            v-tooltip="{
              content: Translator.trans('explanation.territory.help.draw', {
                drawTool: Translator.trans('map.territory.define')
              }),
              container: '#DpOlMap'
            }"
            data-cy="defineMapTerritory"
            :draw-style="{
              fillColor: 'rgba(0,0,0,0.1)',
              strokeColor: '#000',
              imageColor: '#d4004b',
              strokeLineDash: [4,4],
              strokeLineWidth: 3
            }"
            :features="initTerritory"
            icon-class="fa fa-pencil-square-o"
            :label="Translator.trans('map.territory.define')"
            name="Territory"
            render-control
            type="Polygon"
            @layerFeatures:changed="updateTerritory" />
          <dp-ol-map-edit-feature target="Territory" />
          <dp-contextual-help
            class="float-right"
            :text="Translator.trans('explanation.territory.desc')" />
        </div>

        <div
          v-if="hasPermission('area_procedure_adjustments_general_location')"
          class="border--bottom u-pv-0_5 u-mb-0_5 flow-root">
          <i
            aria-hidden="true"
            class="fa fa-map-marker u-ml-0_25 color--grey-light"/>
          <dp-ol-map-draw-feature
            data-cy="setMapRelation"
            :features="procedureCoordinatesFeature"
            :label="Translator.trans('map.relation.set')"
            name="Coordinates"
            render-control
            type="Point"
            @layerFeatures:changed="updateCoordinates" />
          <dp-contextual-help
            class="float-right"
            :text="Translator.trans('text.mapsection.hint')" />
        </div>
        <template v-else>
          <dp-ol-map-draw-feature
            class="u-mb-0_5"
            :features="procedureCoordinatesFeature"
            :label="Translator.trans('map.relation.set')"
            name="Coordinates"
            type="Point"
            @layerFeatures:changed="updateCoordinates" />
        </template>

        <dp-ol-map-drag-zoom class="u-mb-0_5" />
      </template>
    </dp-ol-map>
  </div>
</template>

<script>
import { DpContextualHelp } from '@demos-europe/demosplan-ui'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'
import DpOlMapDragZoom from '@DpJs/components/map/map/DpOlMapDragZoom'
import DpOlMapDrawFeature from '@DpJs/components/map/map/DpOlMapDrawFeature'
import DpOlMapEditFeature from '@DpJs/components/map/map/DpOlMapEditFeature'
import DpOlMapLayerVector from '@DpJs/components/map/map/DpOlMapLayerVector'
import DpOlMapSetExtent from '@DpJs/components/map/map/DpOlMapSetExtent'

export default {
  name: 'MapView',

  components: {
    DpContextualHelp,
    DpOlMap,
    DpOlMapDragZoom,
    DpOlMapDrawFeature,
    DpOlMapEditFeature,
    DpOlMapLayerVector,
    DpOlMapSetExtent
  },

  props: {
    /* GeoJSON object */
    boundingBox: {
      type: Object,
      required: false,
      default: () => ({})
    },

    defaultAttribution: {
      type: String,
      required: false,
      default: ''
    },

    /* GeoJSON object */
    mapExtent: {
      type: Object,
      required: false,
      default: () => ({})
    },

    maxExtent: {
      type: Array,
      required: false,
      default: () => []
    },

    scales: {
      type: Array,
      required: false,
      default: () => []
    },

    procedureCoordinates: {
      required: false,
      type: String,
      default: ''
    },

    procedureId: {
      required: false,
      type: String,
      default: ''
    },

    procedureTerritory: {
      required: false,
      type: String,
      default: '{}'
    }
  },

  data () {
    return {
      coordinate: this.procedureCoordinates.split(','),
      drawingStyles: {
        mapExtent: JSON.stringify({
          fillColor: 'rgba(0,0,0,0.1)',
          strokeColor: '#000',
          imageColor: '#fff',
          strokeLineDash: [4, 4],
          strokeLineWidth: 3
        })
      },
      initTerritory: JSON.parse(this.procedureTerritory),
      isActive: '',
      territory: JSON.parse(this.procedureTerritory)
    }
  },

  computed: {
    procedureCoordinatesFeature () {
      if (this.procedureCoordinates !== '') {
        return {
          type: 'FeatureCollection',
          features: [{
            type: 'Feature',
            geometry: {
              type: 'Point',
              coordinates: this.center
            }
          }]
        }
      } else {
        return {}
      }
    },

    center () {
      if (this.procedureCoordinates) {
        const array = this.procedureCoordinates.split(',')
        return [
          Number(array[0]),
          Number(array[1])
        ]
      } else {
        return false
      }
    }
  },

  watch: {
    $props: {
      handler () {
        this.$refs.map.updateMapInstance()
      },
      deep: true
    }
  },

  methods: {
    updateTerritory (data) {
      this.territory = JSON.parse(data)
    },

    updateCoordinates (data) {
      const features = JSON.parse(data).features
      if (JSON.parse(data).features.length > 0) {
        this.coordinate = features[0].geometry.coordinates
      }
    },

    setExtent (data) {
      this.$emit('update', data)
    }
  }
}
</script>
