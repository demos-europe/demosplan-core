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
    <input
      name="r_territory"
      type="hidden"
      :value="JSON.stringify(territory)">

    <input
      name="r_coordinate"
      type="hidden"
      :value="coordinate">

    <dp-ol-map
      ref="map"
      :options="{
        autoSuggest: false,
        procedureExtent: false,
        initialExtent: true,
        initCenter: center
      }"
      :procedure-id="procedureId">
      <template v-slot:controls>
        <div class="border--bottom u-pv-0_5 flow-root">
          <i
            aria-hidden="true"
            class="fa fa-map u-ml-0_25 color--grey-light" />
          <dp-ol-map-set-extent
            data-cy="mapDefaultBounds"
            translation-key="map.default.bounds"
            @extentSet="data => setExtent({ field: 'mapExtend_of_project_epsg25832', extent: data })" />
          <dp-ol-map-set-extent
            data-cy="boundsApply"
            translation-key="bounds.apply"
            @extentSet="data => setExtent({ field: 'bbox_of_project_epsg25832', extent: data })" />
          <i
            v-tooltip="{ content: Translator.trans('text.mapsection'), container: '#DpOlMap' }"
            :aria-label="Translator.trans('contextual.help')"
            class="fa fa-question-circle float-right" />
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
          <i
            v-tooltip="{
              content: Translator.trans('explanation.territory.desc'),
              container: '#DpOlMap'
            }"
            :aria-label="Translator.trans('contextual.help')"
            class="fa fa-question-circle float-right" />
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
          <i
            v-tooltip="{
              content: Translator.trans('text.mapsection.hint'),
              container: '#DpOlMap'
            }"
            :aria-label="Translator.trans('contextual.help')"
            class="fa fa-question-circle float-right" />
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
import DpOlMap from '@DpJs/components/map/map/DpOlMap'
import DpOlMapDragZoom from '@DpJs/components/map/map/DpOlMapDragZoom'
import DpOlMapDrawFeature from '@DpJs/components/map/map/DpOlMapDrawFeature'
import DpOlMapEditFeature from '@DpJs/components/map/map/DpOlMapEditFeature'
import DpOlMapSetExtent from '@DpJs/components/map/map/DpOlMapSetExtent'

export default {
  name: 'DpMapView',

  components: {
    DpOlMap,
    DpOlMapDragZoom,
    DpOlMapSetExtent,
    DpOlMapDrawFeature,
    DpOlMapEditFeature
  },

  props: {
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
      document.querySelector('p[data-coordinates="' + data.field + '"]').innerText = data.extent
      document.querySelector('input[data-coordinates="' + data.field + '"]').setAttribute('value', data.extent)
    }
  }
}
</script>
