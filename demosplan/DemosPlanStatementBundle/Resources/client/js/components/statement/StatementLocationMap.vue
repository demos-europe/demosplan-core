<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <h2
      class="u-mb-0_75"
      v-text="heading">
    </h2>

    <div class="c-slidebar__content overflow-y-auto u-mr">
      <dp-ol-map
        :procedure-id="procedureId"
        :options="{
          autoSuggest: false
      }">
        <template v-if="hasPermission('feature_segment_polygon_set')">
          <dp-ol-map-draw-feature
            data-cy="setMapRelation"
            ref="drawPoint"
            :features="segmentPointFeature"
            icon
            icon-class="fa-map-marker u-mb-0_25 font-size-h2"
            name="Point"
            render-control
            :title="Translator.trans('map.relation.set')"
            type="Point"
            @layerFeaturesChanged="updatePoint" />
          <dp-ol-map-draw-feature
            data-cy="setMapLine"
            ref="drawLine"
            icon
            icon-class="fa-minus u-mb-0_25 font-size-h2"
            name="Line"
            render-control
            :title="Translator.trans('statement.map.draw.mark_line')"
            type="LineString"
            @layerFeaturesChanged="updateLine" />
          <dp-ol-map-draw-feature
            data-cy="setMapTerritory"
            ref="drawPolygon"
            icon
            icon-class="fa-pencil-square-o u-mb-0_25 font-size-h2"
            name="Polygon"
            render-control
            :title="Translator.trans('statement.map.draw.mark_polygon')"
            type="Polygon"
            @layerFeaturesChanged="updatePolygon" />
          <button
            :title="Translator.trans('statement.map.draw.drop_all')"
            class="btn--blank u-ml-0_5 o-link--default weight--bold"
            type="button"
            @click="resetMapConfirm">
            <i class="fa fa-eraser u-mb-0_25 font-size-h2"></i>
          </button>
        </template>
      </dp-ol-map>
      <dp-button-row
        class="u-mt"
        primary
        secondary
        @primary-action="save"
        @secondary-action="closeSlidebar" />
    </div>
  </div>
</template>

<script>
import {mapActions, mapGetters, mapMutations, mapState} from "vuex"
import DpButtonRow from '@DpJs/components/core/DpButtonRow'
import DpOlMap from '@DemosPlanMapBundle/components/map/DpOlMap'
import DpOlMapDrawFeature from '@DemosPlanMapBundle/components/map/DpOlMapDrawFeature'
import DpOlMapEditFeature from '@DemosPlanMapBundle/components/map/DpOlMapEditFeature'

export default {
  name: 'StatementLocationMap',

  components: {
    DpButtonRow,
    DpOlMap,
    DpOlMapDrawFeature,
    DpOlMapEditFeature
  },

  data () {
    return {
      lineData: null,
      mapData: null,
      pointData: null,
      polygonData: null,
      segmentPoint: '',
    }
  },

  computed: {
    ...mapState('statementSegment', {
      segments: 'items'
    }),

    ...mapGetters('segmentSlidebar', [
      'map',
      'procedureId',
      'statementId'
    ]),

    center () {
      if (this.segmentPoint) {
        const array = this.segmentPoint.split(',')
        return [
          Number(array[0]),
          Number(array[1])
        ]
      } else {
        return false
      }
    },

    heading () {
      return Translator.trans('segment') + ' ' + this.segment?.attributes.externId + ' - ' + Translator.trans('public.participation.relation')
    },

    segmentPointFeature () {
      if (this.segmentPoint !== '') {
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

    segment () {
      return this.segments[this.map.segmentId] || null
    }
  },

  methods: {
    ...mapMutations('segmentSlidebar', [
      'setContent'
    ]),

    ...mapActions('statementSegment', {
      updateSegment: 'update'
    }),

    closeSlidebar () {
      // trigger click event for SideNav.js
      document.querySelector('[data-slidebar-hide]').click()
    },

    getMapData () {
      let mapData = {
        "type":"FeatureCollection",
        "features":[]
      }

      const mapDataCollection = [this.lineData, this.pointData, this.polygonData]
      mapDataCollection.forEach((data) => {
        if (data) {
          data['features'].forEach((feature) => {
            mapData['features'].push(feature)
          })
        }
      })

      return JSON.stringify(mapData)
    },

    resetCurrentMap () {
      this.$refs.drawPoint.clearAll()
      this.$refs.drawLine.clearAll()
      this.$refs.drawPolygon.clearAll()
    },

    resetMapConfirm () {
      if (confirm(Translator.trans('map.territory.removeAll.confirmation'))) {
        this.resetCurrentMap()
      }
    },

    // TO DO Implement API Call
    save () {
      const mapData = this.getMapData()
    },

    updateLine (data) {
      this.lineData = JSON.parse(data)
    },

    updatePoint (data) {
      this.pointData = JSON.parse(data)
    },

    updatePolygon (data) {
      this.polygonData = JSON.parse(data)
    },


  }
}
</script>
