<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <h2
      class="u-mb-0_75"
      v-text="heading" />

    <div class="c-slidebar__content overflow-y-auto u-mr">
      <dp-ol-map
        ref="map"
        :procedure-id="procedureId"
        :map-options="{
          procedureMaxExtent: mapData.mapExtent ?? []
        }"
        :options="{
          autoSuggest: false,
          defaultAttribution: mapData.copyright,
          initialExtent: mapData.boundingBox ?? [],
        }">
        <template v-if="hasPermission('feature_segment_polygon_set')">
          <dp-ol-map-draw-feature
            ref="drawPoint"
            data-cy="setMapRelation"
            :features="pointData"
            icon
            icon-class="fa-map-marker u-mb-0_25 font-size-h2"
            name="Point"
            :options="{ multiplePoints: true }"
            render-control
            v-tooltip="{
              content: Translator.trans('map.relation.set'),
              classes: 'z-ultimate'
            }"
            type="Point"
            @layerFeatures:changed="data => updateDrawings('Point', data)" />
          <dp-ol-map-draw-feature
            data-cy="setMapLine"
            ref="drawLine"
            :features="lineData"
            icon
            icon-class="fa-minus u-mb-0_25 font-size-h2"
            name="Line"
            render-control
            v-tooltip="{
              content: Translator.trans('statement.map.draw.mark_line'),
              classes: 'z-ultimate'
            }"
            type="LineString"
            @layerFeatures:changed="data => updateDrawings('LineString', data)" />
          <dp-ol-map-draw-feature
            ref="drawPolygon"
            data-cy="setMapTerritory"
            :features="polygonData"
            icon
            icon-class="fa-square-o u-mb-0_25 font-size-h2"
            name="Polygon"
            render-control
            v-tooltip="{
              content: Translator.trans('statement.map.draw.mark_polygon'),
              classes: 'z-ultimate'
            }"
            type="Polygon"
            @layerFeatures:changed="data => updateDrawings('Polygon', data)" />
          <dp-ol-map-edit-feature
            class="border--left u-ml-0_25"
            :target="['Polygon', 'Line', 'Point']">
            <template v-slot:editButtonDesc>
              <i
                class="fa fa-pencil-square-o u-mb-0_25 font-size-h2"
                aria-hidden="true" />
            </template>
            <template v-slot:removeButtonDesc>
              <i
                class="fa fa-eraser u-mb-0_25 font-size-h2"
                aria-hidden="true" />
            </template>
            <template v-slot:removeAllButtonDesc>
              <i
                class="fa fa-trash u-mb-0_25 font-size-h2"
                aria-hidden="true" />
            </template>
          </dp-ol-map-edit-feature>
        </template>
      </dp-ol-map>
      <dp-button-row
        class="u-mt"
        :disabled="!hasChanges"
        primary
        secondary
        @primary-action="save"
        @secondary-action="closeSlidebar" />
    </div>
  </div>
</template>

<script>
import { checkResponse, DpButtonRow } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'
import DpOlMapDrawFeature from '@DpJs/components/map/map/DpOlMapDrawFeature'
import DpOlMapEditFeature from '@DpJs/components/map/map/DpOlMapEditFeature'
import { extend } from 'ol/extent'
import { fromExtent } from 'ol/geom/Polygon'

export default {
  name: 'SegmentLocationMap',

  components: {
    DpButtonRow,
    DpOlMap,
    DpOlMapDrawFeature,
    DpOlMapEditFeature,
  },

  props: {
    mapData: {
      type: Object,
      required: false,
      default: () => ({})
    },

    procedureId: {
      type: String,
      required: true
    },

    segmentId: {
      type: String,
      required: true
    },

    statementId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      currentPolygons: [],
      hasChanges: true,
      initPolygons: []
    }
  },

  computed: {
    ...mapState('statementSegment', {
      segments: 'items'
    }),

    ...mapState('segmentSlidebar', ['slidebar']),

    pointData () {
      return {
        type: 'FeatureCollection',
        features: this.initPolygons.filter(f => f.geometry.type === 'Point') || []
      }
    },

    features () {
      /*
       *  Transform the value that is saved as a string into valid GeoJSON
       *  to be able to use it with a generic vector layer component
       */
      return {
        boundingBox: this.mapData.boundingBox
          ? {
              type: 'Feature',
              geometry: {
                type: 'Polygon',
                coordinates: fromExtent(JSON.parse(`[${this.mapData.boundingBox}]`)).getCoordinates()
              }
            }
          : null
      }
    },

    lineData () {
      return {
        type: 'FeatureCollection',
        features: this.initPolygons.filter(f => f.geometry.type === 'LineString') || []
      }
    },

    polygonData () {
      return {
        type: 'FeatureCollection',
        features: this.initPolygons.filter(f => f.geometry.type === 'Polygon') || []
      }
    },

    heading () {
      return `${Translator.trans('segment')} ${this.segment?.attributes.externId} - ${Translator.trans('public.participation.relation')}`
    },

    featuresObject () {
      return {
        type: 'FeatureCollection',
        features: this.currentPolygons
      }
    },

    segment () {
      return this.segments[this.segmentId] || null
    }
  },

  watch: {
    segmentId (newVal) {
      if (newVal) {
        this.setInitDrawings()
        if (this.featuresObject.features.length > 0) {
          this.$nextTick(() => {
            this.setCenterAndExtent()
          })
        } else if (this.mapData.boundingBox) {
          this.$nextTick(() => {
            this.setInitExtent()
          })
        }
      }
    }
  },

  methods: {
    ...mapMutations('statementSegment', ['setItem']),

    ...mapActions('statementSegment', {
      saveSegmentAction: 'save'
    }),

    clearTools () {
      this.$refs.drawPoint.clearAll()
      this.$refs.drawLine.clearAll()
      this.$refs.drawPolygon.clearAll()
    },

    closeSlidebar () {
      this.$root.$emit('hide-slidebar')
    },

    resetCurrentMap () {
      this.clearTools()
      if (this.slidebar.isOpen) {
        this.setInitDrawings()
      }

      this.$nextTick(() => {
        this.$refs.map.updateMapInstance()
      })
    },

    save () {
      this.setItem({
        ...this.segment,
        attributes: {
          ...this.segment.attributes,
          polygon: JSON.stringify(this.featuresObject)
        }
      })

      return this.saveSegmentAction(this.segmentId)
        .then(checkResponse)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
        })
    },

    setInitExtent () {
      this.$refs.map.map.updateSize()
      this.$nextTick(() => {
        this.$refs.map.map.getView().fit(JSON.parse(`[${this.mapData.boundingBox}]`), { size: this.$refs.map.map.getSize() })
      })
    },

    /*
     * Center the map around all drawings and zoom to the combined extent.
     */
    setCenterAndExtent () {
      const extentPolygon = this.$refs.drawPolygon.getExtent()
      const extentPoint = this.$refs.drawPoint.getExtent()
      const extentLine = this.$refs.drawLine.getExtent()

      let completeExtend = extend(extentPolygon, extentPoint)
      completeExtend = extend(completeExtend, extentLine)

      this.$refs.map.map.updateSize()
      this.$nextTick(() => {
        this.$refs.map.map.getView().fit(completeExtend, { size: this.$refs.map.map.getSize() })
      })
    },

    setInitDrawings () {
      if (this.segmentId === '') {
        return
      }

      this.initPolygons = JSON.parse(this.segments[this.segmentId].attributes.polygon || '{ "features": [] }').features
      this.currentPolygons = JSON.parse(JSON.stringify(this.initPolygons))
    },

    updateDrawings (type, data) {
      this.currentPolygons = this.currentPolygons.filter(f => f.geometry.type !== type)
      this.currentPolygons = [...this.currentPolygons, ...JSON.parse(data).features]
    }
  }
}
</script>
