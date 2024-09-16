<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout u-pb">
    <div class="layout__item w-1/1">
      <dp-input
        v-if="hasPermission('feature_map_max_extent')"
        id="mapExtent"
        v-model="mapExtent"
        data-cy="mapExtent"
        disabled
        :label="{
          text: Translator.trans('max_extent')
        }" />
      <dp-input
        id="boundingBox"
        v-model="boundingBox"
        data-cy="boundingBox"
        disabled
        :label="{
          text: Translator.trans('initial_extent')
        }" />
    </div>
    <map-view
      ref="mapView"
      :bounding-box="boundingBoxAsPolygon"
      class="layout__item w-1/1 u-pb"
      :default-attribution="procedureMapSettings.attributes.copyright"
      :map-extent="mapExtentAsPolygon"
      :max-extent="procedureMapSettings.attributes.defaultMapExtent"
      :procedure-id="procedureId"
      :procedure-coordinates="procedureMapSettings.attributes.coordinate"
      :procedure-init-territory="Array.isArray(procedureMapSettings.attributes.territory) ? {} : procedureMapSettings.attributes.territory"
      :scales="procedureMapSettings.attributes.availableScales"
      @field:update="setField" />

    <div class="layout__item">
      <map-admin-scales
        :available-scales="procedureMapSettings.attributes.availableScales"
        class="u-mb"
        :selected-scales="procedureMapSettings.attributes.scales || []"
        @update="value => procedureMapSettings.attributes.scales = value"
        @suitableScalesChange="value => areScalesSuitable = value" />

      <dp-input
        v-if="!procedureMapSettings.attributes.featureInfoUrl.global && hasPermission('feature_map_feature_info')"
        v-model="procedureMapSettings.attributes.informationUrl"
        id="informationURL"
        class="u-mb"
        data-cy="map:informationUrl"
        :label="{
          text: Translator.trans('url.information'),
          hint: Translator.trans('url.information.hint', { buttonlabel: 'map.getfeatureinfo.label' })
        }" />

      <dp-input
        v-if="hasPermission('feature_map_attribution')"
        id="copyright"
        v-model="procedureMapSettings.attributes.copyright"
        class="u-mb"
        data-cy="map:mapCopyright"
        :label="{
          text: Translator.trans('map.attribution'),
          hint: Translator.trans('map.attribution.placeholder')
        }" />
    </div>

    <dp-checkbox
      v-if="hasPermission('feature_layer_groups_alternate_visibility')"
      id="enableLayerGroupsAlternateVisibility"
      v-model="procedureMapSettings.attributes.showOnlyOverlayCategory"
      :checked="procedureMapSettings.attributes.showOnlyOverlayCategory"
      :label="{
        bold: true,
        hint: Translator.trans('explanation.gislayer.layergroup.toggle.alternating.visibility.extended'),
        text: Translator.trans('explanation.gislayer.layergroup.toggle.alternating.visibility')
      }" />

    <div class="layout__item u-1-of-1 text-right u-mt-0_5 space-inline-s">
      <dp-button
        data-cy="save"
        :disabled="!areScalesSuitable"
        name="saveConfig"
        :text="Translator.trans('save')"
        type="submit"
        @click="() => save()" />
      <dp-button
        v-if="hasPermission('area_admin_single_document')"
        data-cy="saveAndReturn"
        :disabled="!areScalesSuitable"
        name="submit_item_return_button"
        :text="Translator.trans('save.and.return.to.list')"
        type="submit"
        @click="() => save(true)" />
      <dp-button
        v-if="hasPermission('area_admin_single_document')"
        color="secondary"
        data-cy="abort"
        :href="Routing.generate('DemosPlan_element_administration', { procedure: procedureId })"
        :text="Translator.trans('abort')" />
      <dp-button
        v-else
        color="secondary"
        data-cy="reset"
        :text="Translator.trans('reset')"
        @click="reset" />
    </div>
  </div>
</template>
<script>
import { checkResponse, dpApi, DpButton, DpCheckbox, DpInput } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import { Attribution } from 'ol/control'
import convertExtentToObject from '../map/utils/convertExtentToObject'
import { fromExtent } from 'ol/geom/Polygon'
import MapAdminScales from './MapAdminScales'
import MapView from '@DpJs/components/map/map/MapView'

export default {
  name: 'MapAdmin',

  components: {
    DpButton,
    DpCheckbox,
    DpInput,
    MapAdminScales,
    MapView
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      areScalesSuitable: true,
      drawingStyles: {
        territory: JSON.stringify({
          fillColor: 'rgba(0,0,0,0.1)',
          strokeColor: '#000',
          imageColor: '#fff',
          strokeLineDash: [4, 4],
          strokeLineWidth: 3
        })
      },
      procedureMapSettings: {
        id: '',
        type: 'ProcecdureMapSetting',
        attributes: {
          coordinate: [],
          copyright: '',
          defaultMapExtent: [],
          defaultBoundingBox: [],
          featureInfoUrl: { global: false },
          informationUrl: '',
          showOnlyOverlayCategory: false,
          mapExtent: [],
          boundingBox: [],
          scales: [],
          territory: {}
        }
      }
    }
  },

  computed: {
    ...mapState('ProcedureMapSettings', {
      originalProcedureMapSettings: 'procedureMapSettings'
    }),

    attributionControl () {
      return new Attribution({ collapsible: false })
    },

    boundingBox () {
      if (this.procedureMapSettings.attributes.boundingBox?.length < 1 && this.procedureMapSettings.attributes.defaultBoundingBox?.length < 1) {
        return Translator.trans('initial_extent.not.set')
      }

      return this.procedureMapSettings.attributes.boundingBox[0] ? this.procedureMapSettings.attributes.boundingBox.join(',') : ''
    },

    boundingBoxAsPolygon () {
      if (this.procedureMapSettings.attributes.boundingBox?.length < 1) {
        return null
      }

      return {
        type: 'Feature',
        geometry: {
          type: 'Polygon',
          coordinates: fromExtent(this.procedureMapSettings.attributes.boundingBox).getCoordinates()
        }
      }
    },

    mapExtent () {
      if (this.procedureMapSettings.attributes.mapExtent?.length < 1 && this.procedureMapSettings.attributes.procedureDefaultMapExtent?.length < 1) {
        return Translator.trans('max_extent.not.set')
      }

      return this.procedureMapSettings.attributes.mapExtent[0] ? this.procedureMapSettings.attributes.mapExtent.join(',') : ''
    },

    mapExtentAsPolygon () {
      if (this.procedureMapSettings.attributes.mapExtent?.length < 1) {
        return null
      }

      return {
        type: 'Feature',
        geometry: {
          type: 'Polygon',
          coordinates: fromExtent(this.procedureMapSettings.attributes.mapExtent).getCoordinates()
        }
      }
    }
  },

  methods: {
    ...mapActions('ProcedureMapSettings', ['fetchProcedureMapSettings']),

    reset () {
      this.procedureMapSettings = this.originalProcedureMapSettings
    },

    save (returnToOverview = false) {
      const url = Routing.generate('api_resource_update', { resourceType: 'ProcedureMapSetting', resourceId: this.procedureMapSettings.id })
      const updateData = this.procedureMapSettings.attributes

      const payload = {
        data: {
          id: this.procedureMapSettings.id,
          type: 'ProcedureMapSetting',
          attributes: {
            scales: updateData.scales.map(scale => scale.value)
          }
        }
      }

      if (updateData.mapExtent.length > 0) {
        payload.data.attributes.mapExtent = convertExtentToObject(updateData.mapExtent)
      }

      if (updateData.boundingBox.length > 0) {
        payload.data.attributes.boundingBox = convertExtentToObject(updateData.boundingBox)
      }

      if (hasPermission('area_procedure_adjustments_general_location')) {
        payload.data.attributes.coordinate = convertExtentToObject(updateData.coordinate)
      }

      if (hasPermission('feature_layer_groups_alternate_visibility')) {
        payload.data.attributes.layerGroupsAlternateVisibility = updateData.layerGroupsAlternateVisibility
      }

      if (hasPermission('feature_map_use_territory')) {
        payload.data.attributes.territory = updateData.territory
      }

      if (hasPermission('feature_map_feature_info')) {
        payload.data.attributes.informationUrl = updateData.informationUrl
      }

      if (hasPermission('feature_map_attribution')) {
        payload.data.attributes.copyright = updateData.copyright
      }

      dpApi.patch(url, {}, payload)
        .then(checkResponse)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('text.mapsection.updated'))
          this.$refs.mapView.$refs.map.getMapOptions()

          if (returnToOverview) {
            window.location.href = Routing.generate('DemosPlan_element_administration', { procedure: this.procedureId })
          }
        })
    },

    setField ({ field, data }) {
      this.procedureMapSettings.attributes[field] = data
    }
  },

  async mounted () {
    const settings = await this.fetchProcedureMapSettings(this.procedureId)
    this.procedureMapSettings = JSON.parse(JSON.stringify(settings))
  }
}
</script>
