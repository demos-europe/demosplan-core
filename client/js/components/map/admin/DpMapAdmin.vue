<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout u-pb">
<!--    {# TO DO: Add permission #}-->
    <dp-input
      id="maxExtent"
      v-model="maxExtent"
      data-cy="maxExtent"
      disabled
      :label="{
        text: Translator.trans('max_extent')
      }" />
<!--    {# Startkartenausschnitt #}-->
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
        :selected-scales="JSON.parse(JSON.stringify(selectedScales))"
        @change="value => areScalesSuitable = value" />
    </div>

<!--    {# TO DO: Add permission #}-->
    <div class="u-mb">
      <dp-input
        v-if="!mapGlobals.featureInfoUrl.global"
        id="informationURL"
        v-model="mapGlobals.informationUrl"
        :label="{
          text: Translator.trans('url.information'),
          hint: Translator.trans('url.information.hint', { buttonlabel: 'map.getfeatureinfo.label' })
        }" />
    </div>
<!--    {# Copyright hint #}-->
    <div class="u-mb">
      <dp-input
        id="copyright"
        v-model="mapGlobals.copyright"
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
import { DpCheckbox, DpInput } from '@demos-europe/demosplan-ui'
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
    procedure: {
      type: String,
      required: true
    }
  },

  data () {
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
      selectedScales: [],
      territory: ''
    }
  },

  computed: {
    maxExtent () {
      if (this.mapOptions.procedureMaxExtent === this.mapOptions.procedureDefaultMaxExtent) {
        return Translator.trans('max_extent.not.set')
      }

      return this.mapOptions.procedureMaxExtent.join(',')
    },

    initialExtent () {
      if (this.mapOptions.procedureInitialExtent === this.mapOptions.procedureDefaultInitialExtent) {
        return Translator.trans('initial_extent.not.set')
      }

      return this.mapOptions.procedureInitialExtent.join(',')
    }
  }
}
</script>
