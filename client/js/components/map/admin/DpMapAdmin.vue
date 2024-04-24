<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout u-pb">
<!--    {# TO DO: Add permission, This boundingBox in BE! #}-->
    <dp-input
      id="maxExtent"
      v-model="maxExtent"
      data-cy="maxExtent"
      disabled
      :label="{
        text: Translator.trans('max_extent')
      }" />
<!--    {# Startkartenausschnitt, This is mapExtent in BE! #}-->
    <dp-input
      id="initialExtent"
      v-model="initialExtent"
      data-cy="initialExtent"
      disabled
      :label="{
        text: Translator.trans('initial_extent')
      }" />
    <dp-map-view
      class="layout__item u-1-of-1 u-pb"
      :default-attribution="mapGlobals.copyright"
      :procedure-id="procedure"
      :procedure-coordinates="mapGlobals.procedure.settings.coordinate"
      :procedure-territory="territory" />
    <div class="u-mb">
      <dp-map-admin-scales
        :available-scales="JSON.parse(JSON.stringify(availableScales))"
        :selected-scales="JSON.parse(JSON.stringify(procedureSettings.scales))"
        @change="value => areScalesSuitable = value" />
    </div>

<!--    {# TO DO: Add permission #}-->
    <div class="u-mb">
      <dp-input
        v-if="!mapGlobals.featureInfoUrl.global"
        id="informationURL"
        v-model="procedureSettings.informationUrl"
        :label="{
          text: Translator.trans('url.information'),
          hint: Translator.trans('url.information.hint', { buttonlabel: 'map.getfeatureinfo.label' })
        }" />
    </div>
<!--    {# Copyright hint #}-->
    <div class="u-mb">
      <dp-input
        id="copyright"
        v-model="procedureSettings.copyright"
        :label="{
          text: Translator.trans('map.attribution'),
          hint: Translator.trans('map.attribution.placeholder')
        }" />
    </div>
    <dp-checkbox
      v-if="hasPermission('feature_layer_groups_alternate_visibility')"
      id="enableLayerGroupsAlternateVisibility"
      v-model="mapGlobals.layerGroupsAlternateVisibility"
      :checked="mapGlobals.layerGroupsAlternateVisibility"
      :label="{
        bold: true,
        hint: Translator.trans('explanation.gislayer.layergroup.toggle.alternating.visibility.extended'),
        text: Translator.trans('explanation.gislayer.layergroup.toggle.alternating.visibility')
      }" />
    <div class="layout__item u-1-of-1 text-right u-mt-0_5 space-inline-s">
      <input
        class="btn btn--primary"
        :disabled="!areScalesSuitable"
        type="submit"
        name="saveConfig"
        :value="Translator.trans('save')">
      <input
        class="btn btn--primary"
        :disabled="!areScalesSuitable"
        type="submit"
        name="submit_item_return_button"
        :value="Translator.trans('save.and.return.to.list')">
      <a class="btn btn--secondary" :href="Routing.generate('DemosPlan_element_administration', { 'procedure':procedure })">
        {{ Translator.trans('abort') }}
      </a>
    </div>
  </div>
</template>
<script>
import { DpApi, DpCheckbox, DpInput } from '@demos-europe/demosplan-ui'
import DpMapAdminScales from './DpMapAdminScales'
import DpMapView from '@DpJs/components/map/map/DpMapView'

export default {
  name: 'DpMapAdmin',

  components: {
    DpCheckbox,
    DpInput,
    DpMapView,
    DpMapAdminScales
  },

  props: {
  },

  data () {
    // TO DO: Adjust, this was a first draft
    return {
      areScalesSuitable: true,
      availableScales: [],
      mapGlobals: {
        copyright: '',
        featureInfoUrl: {
          global: false
        },
        informationUrl: '',
        layerGroupsAlternateVisibility: false,
        procedure: {
          settings: {
            coordinate: ''
          }
        }
      },
      mapOptions: {
        procedureInitialExtent: [],
        procedureMaxExtent: [],
        procedureDefaultInitialExtent: [],
        procedureDefaultMaxExtent: []
      },
      procedureSettings: {},
      territory: ''
    }
  },

  computed: {
    // TO DO: Adjust to BE implementation
    maxExtent () {
      if (this.procedureSettings.procedureMaxExtent === this.mapOptions.procedureDefaultMaxExtent) {
        return Translator.trans('max_extent.not.set')
      }

      return this.procedureSettings.procedureMaxExtent.join(',')
    },

    // TO DO: Adjust to BE implementation
    initialExtent () {
      if (this.procedureSettings.procedureInitialExtent === this.mapOptions.procedureDefaultInitialExtent) {
        return Translator.trans('initial_extent.not.set')
      }

      return this.procedureSettings.procedureInitialExtent.join(',')
    }
  },

  methods: {
    fetchInitialData () {
      this.fetchProcedureSettings()
    },

    fetchProcedureSettings () {
      const url = Routing.generate('api_resource_list', { procedureId: this.procedureId, resourceType: 'Procedure' })
      const params = {
        fields: {
          ProcedureSettings: [
            'boundingBox',
            'coordinate',
            'mapExtent',
            'scales',
            'informationUrl',
            'copyright',
            'publicAvailableScales'
          ].join()
        },
        include: 'ProcedureSettings'
      }

      DpApi.get(url, params)
        .then(response => {
          this.procedureSettings = response.data
        })
    }
  },

  mounted () {
    this.fetchInitialData()
  }
}
</script>
