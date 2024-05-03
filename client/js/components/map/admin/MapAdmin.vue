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
      class="layout__item u-1-of-1 u-pb"
      :default-attribution="procedureMapSettings.attributes.copyright"
      :procedure-id="procedureId"
      :procedure-coordinates="coordinate"
      :procedure-territory="procedureMapSettings.attributes.territory"
      :scales="procedureMapSettings.attributes.availableScales"
      @update="setExtent" />

    <div class="layout__item">
      <map-admin-scales
        :available-scales="procedureMapSettings.attributes.availableScales"
        class="u-mb"
        :selected-scales="procedureMapSettings.attributes.scales || []"
        @update="value => procedureMapSettings.attributes.scales = value"
        @suitableScalesChange="value => areScalesSuitable = value" />

      <dp-input
        v-if="!procedureMapSettings.attributes.featureInfoUrl.global"
        v-model="procedureMapSettings.attributes.informationUrl"
        id="informationURL"
        class="u-mb"
        :label="{
          text: Translator.trans('url.information'),
          hint: Translator.trans('url.information.hint', { buttonlabel: 'map.getfeatureinfo.label' })
        }" />

      <dp-input
        id="copyright"
        v-model="procedureMapSettings.attributes.copyright"
        class="u-mb"
        :label="{
          text: Translator.trans('map.attribution'),
          hint: Translator.trans('map.attribution.placeholder')
        }" />
    </div>

    <dp-checkbox
      v-if="hasPermission('feature_layer_groups_alternate_visibility')"
      id="enableLayerGroupsAlternateVisibility"
      v-model="procedureMapSettings.attributes.layerGroupsAlternateVisibility"
      :checked="procedureMapSettings.attributes.layerGroupsAlternateVisibility"
      :label="{
        bold: true,
        hint: Translator.trans('explanation.gislayer.layergroup.toggle.alternating.visibility.extended'),
        text: Translator.trans('explanation.gislayer.layergroup.toggle.alternating.visibility')
      }" />

    <input
      aria-hidden="true"
      name="r_territory"
      type="hidden"
      :value="JSON.stringify(procedureMapSettings.attributes.territory)">

    <input
      aria-hidden="true"
      name="r_coordinate"
      type="hidden"
      :value="coordinate">

    <div class="layout__item u-1-of-1 text-right u-mt-0_5 space-inline-s">
      <input
        class="btn btn--primary"
        :disabled="!areScalesSuitable"
        type="submit"
        name="saveConfig"
        :value="Translator.trans('save')"
        @click="() => save()">
      <input
        v-if="hasPermission('area_admin_single_document')"
        class="btn btn--primary"
        :disabled="!areScalesSuitable"
        type="submit"
        name="submit_item_return_button"
        :value="Translator.trans('save.and.return.to.list')"
        @click="() => save(true)">
      <a
        class="btn btn--secondary"
        :href="Routing.generate('DemosPlan_element_administration', { procedure: procedureId })">
        {{ Translator.trans('abort') }}
      </a>
    </div>
  </div>
</template>
<script>
import { checkResponse, dpApi, DpCheckbox, DpInput } from '@demos-europe/demosplan-ui'
import { Attribution } from 'ol/control'
import convertExtentToFlatArray from '../map/utils/convertExtentToFlatArray'
import { fromExtent } from 'ol/geom/Polygon'
import MapAdminScales from './MapAdminScales'
import MapView from '@DpJs/components/map/map/MapView'

export default {
  name: 'MapAdmin',

  components: {
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

  provide () {
    return {
      olMapState: this.olMapState
    }
  },

  data () {
    return {
      areScalesSuitable: true,
      coordinate: '',
      procedureMapSettings: {
        id: '',
        type: 'ProcecdureMapSetting',
        attributes: {
          copyright: '',
          defaultMapExtent: [],
          defaultBoundingBox: [],
          featureInfoUrl: { global: false },
          informationUrl: '',
          layerGroupsAlternateVisibility: false,
          mapExtent: [],
          boundingBox: [],
          scales: [],
          territory: '{}'
        }
      }
    }
  },

  computed: {
    attributionControl () {
      return new Attribution({ collapsible: false })
    },

    boundingBox () {
      if (this.procedureMapSettings.attributes.boundingBox === this.procedureMapSettings.attributes.defaultBoundingBox) {
        return Translator.trans('initial_extent.not.set')
      }

      return this.procedureMapSettings.attributes.boundingBox[0] ? this.procedureMapSettings.attributes.boundingBox.join(',') : ''
    }
  },

  methods: {
    convertExtentToObject (extentArray) {
      if (extentArray.length < 4) {
        return {
          latitude: extentArray[0],
          longitude: extentArray[1]
        }
      }

      return {
        start: {
          latitude: extentArray[0],
          longitude: extentArray[1]
        },
        end: {
          latitude: extentArray[2],
          longitude: extentArray[3]
        }
      }
    },

    fetchInitialData () {
      const url = Routing.generate('api_resource_get', { resourceId: this.procedureId, resourceType: 'Procedure' })
      const params = {
        fields: {
          Procedure: [
            'mapSetting'
          ].join(),
          ProcedureMapSetting: [
            'availableScales',
            'boundingBox',
            'copyright',
            'defaultBoundingBox',
            'defaultMapExtent',
            'featureInfoUrl',
            'informationUrl',
            'mapExtent',
            'scales'
          ].join()
        },
        include: 'mapSetting'
      }

      if (hasPermission('area_procedure_adjustments_general_location')) {
        params.fields.ProcedureMapSetting.push('coordinate')
      }

      if (hasPermission('feature_map_use_territory')) {
        params.fields.ProcedureMapSetting.push('territory')
      }

      if (hasPermission('feature_layer_groups_alternate_visibility')) {
        params.fields.ProcedureMapSetting.push('layerGroupsAlternateVisibility')
      }

      dpApi.get(url, params)
        .then(response => {
          const data = response.data.included[0].attributes

          this.coordinate = response.data.data.attributes.coordinate ?? ''
          this.procedureMapSettings.id = response.data.included[0].id
          this.procedureMapSettings.attributes = {
            availableScales: data.availableScales.map(scale => ({ label: `1:${scale.toLocaleString('de-DE')}`, value: scale })) ?? [],
            copyright: data.copyright ?? '',
            defaultBoundingBox: convertExtentToFlatArray(data.defaultBoundingBox) ?? [],
            defaultMapExtent: convertExtentToFlatArray(data.defaultMapExtent) ?? [],
            featureInfoUrl: data.featureInfoUrl ?? { global: false },
            informationUrl: data.informationUrl ?? '',
            layerGroupsAlternateVisibility: data.layerGroupsAlternateVisibility ?? false,
            mapExtent: convertExtentToFlatArray(data.mapExtent) ?? [], // Maximum extent of the map
            boundingBox: convertExtentToFlatArray(data.boundingBox) ?? [], // Extent on load of the map
            scales: data.scales.map(scale => ({ label: `1:${scale.toLocaleString()}`, value: scale })) ?? [],
            territory: data.territory ?? '{}'
          }
        })
    },

    save (returnToOverview = false) {
      const url = Routing.generate('api_resource_update', { resourceType: 'ProcedureMapSetting', resourceId: this.procedureMapSettings.id })
      const updateData = this.procedureMapSettings.attributes

      const payload = {
        data: {
          id: this.procedureMapSettings.id,
          type: 'ProcedureMapSetting',
          attributes: {
            boundingBox: this.convertExtentToObject(updateData.boundingBox),
            copyright: updateData.copyright,
            informationUrl: updateData.informationUrl,
            mapExtent: this.convertExtentToObject(updateData.mapExtent),
            scales: updateData.scales.map(scale => scale.value)
          }
        }
      }

      if (hasPermission('feature_layer_groups_alternate_visibility')) {
        payload.data.attributes.layerGroupsAlternateVisibility = updateData.layerGroupsAlternateVisibility
      }

      if (hasPermission('feature_map_use_territory')) {
        payload.data.attributes.territory = updateData.territory
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

    setExtent ({ field, extent }) {
      this.procedureMapSettings.attributes[field] = extent
    }
  },

  mounted () {
    this.fetchInitialData()
  }
}
</script>
