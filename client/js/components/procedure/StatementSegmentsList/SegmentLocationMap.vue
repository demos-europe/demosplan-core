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
      v-text="heading" />

    <div class="c-slidebar__content overflow-y-auto u-mr">
      <dp-ol-map
        :procedure-id="procedureId"
        :options="{
          autoSuggest: false
        }">
        <template v-if="hasPermission('feature_segment_polygon_set')">
          <dp-ol-map-draw-feature
            ref="drawPoint"
            data-cy="setMapRelation"
            :features="drawingsData.point"
            icon
            icon-class="fa-map-marker u-mb-0_25 font-size-h2"
            name="Point"
            render-control
            :title="Translator.trans('map.relation.set')"
            type="Point"
            @layerFeaturesChanged="data => updateDrawings('point', data)" />
          <dp-ol-map-draw-feature
            data-cy="setMapLine"
            ref="drawLine"
            :features="drawingsData.linestring"
            icon
            icon-class="fa-minus u-mb-0_25 font-size-h2"
            name="Line"
            render-control
            :title="Translator.trans('statement.map.draw.mark_line')"
            type="LineString"
            @layerFeaturesChanged="data => updateDrawings('linestring', data)" />
          <dp-ol-map-draw-feature
            ref="drawPolygon"
            data-cy="setMapTerritory"
            :features="drawingsData.polygon"
            icon
            icon-class="fa-square-o u-mb-0_25 font-size-h2"
            name="Polygon"
            render-control
            :title="Translator.trans('statement.map.draw.mark_polygon')"
            type="Polygon"
            @layerFeaturesChanged="data => updateDrawings('polygon', data)" />
          <dp-ol-map-edit-feature :target="['Polygon', 'Line', 'Point']">
            <template v-slot:editButtonDesc>
              <i
                :title="Translator.trans('map.territory.tools.edit')"
                class="fa fa-pencil-square-o u-mb-0_25 font-size-h2"
                aria-hidden="true" />
            </template>
            <template v-slot:removeButtonDesc>
              <i
                :title="Translator.trans('map.territory.tools.removeSelected')"
                class="fa fa-eraser u-mb-0_25 font-size-h2"
                aria-hidden="true" />
            </template>
            <template v-slot:removeAllButtonDesc>
              <i
                :title="Translator.trans('map.territory.tools.removeAll')"
                class="fa fa-trash u-mb-0_25 font-size-h2"
                aria-hidden="true" />
            </template>
          </dp-ol-map-edit-feature>
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
import { mapActions, mapMutations, mapState } from 'vuex'
import { checkResponse } from '@DemosPlanCoreBundle/plugins/DpApi'
import DpButtonRow from '@DpJs/components/core/DpButtonRow'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'
import DpOlMapDrawFeature from '@DpJs/components/map/map/DpOlMapDrawFeature'
import DpOlMapEditFeature from '@DpJs/components/map/map/DpOlMapEditFeature'

export default {
  name: 'SegmentLocationMap',

  components: {
    DpButtonRow,
    DpOlMap,
    DpOlMapDrawFeature,
    DpOlMapEditFeature
  },

  props: {
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
      drawingsData: {},
      mapData: null,
      segmentPoint: ''
    }
  },

  computed: {
    ...mapState('statementSegment', {
      segments: 'items'
    }),

    center () {
      if (this.segmentPoint) {
        const array = this.segmentPoint.split(',')
        return [
          Number(array[0]),
          Number(array[1])
        ]
      }

      return false
    },

    heading () {
      return `${Translator.trans('segment')} ${this.segment?.attributes.externId} - ${Translator.trans('public.participation.relation')}`
    },

    segment () {
      return this.segments[this.segmentId] || null
    }
  },

  watch: {
    segmentId (newVal) {
      if (newVal) {
        this.setInitDrawings()
      }
    }
  },

  methods: {
    ...mapMutations('statementSegment', ['setItem']),

    ...mapActions('statementSegment', {
      saveSegmentAction: 'save'
    }),

    closeSlidebar () {
      // Trigger click event for SideNav.js
      document.querySelector('[data-slidebar-hide]').click()
    },

    getMapData () {
      const mapData = {
        type: 'FeatureCollection',
        features: []
      }

      Object.values(this.drawingsData).forEach((data) => {
        if (data) {
          data.features.forEach((feature) => {
            mapData.features.push(feature)
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

    save () {
      const mapData = this.getMapData()

      this.setItem({
        ...this.segment,
        attributes: {
          ...this.segment.attributes,
          polygon: mapData
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

    setInitDrawings () {
      const initData = JSON.parse(this.segments[this.segmentId].attributes.polygon || '{}')

      if (initData.features) {
        ['Polygon', 'LineString', 'Point'].forEach(type => {
          this.drawingsData[type.toLowerCase()] = {
            type: 'FeatureCollection',
            features: initData.features.filter(f => f.geometry.type === type)
          }
        })
      }
    },

    updateDrawings (type, data) {
      this.drawingsData[type] = JSON.parse(data)
    }
  }
}
</script>
