<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout u-pl">
    <dp-input
      id="r_baseLayerUrl"
      v-model="baseLayerUrl"
      class="u-mb-0_75"
      data-cy="customerSettingsMap:mapBaseURLHint"
      :label="{
        hint: Translator.trans('map.base.url.hint'),
        text: Translator.trans('map.base.url')
      }"
      name="r_baseLayerUrl"
      @input="debounceUpdate" />

    <dp-input
      id="r_baseLayerLayers"
      v-model="baseLayerLayers"
      class="u-mb-0_75"
      data-cy="customerSettingsMap:mapLayerNameHint"
      :label="{
        hint: Translator.trans('map.layer.name.hint'),
        text: Translator.trans('layers')
      }"
      name="r_baseLayerLayers"
      @input="debounceUpdate" />

    <dp-input
      id="r_mapAttribution"
      class="u-mb-0_75"
      data-cy="customerSettingsMap:mapAttribution"
      :label="{
        hint: `${Translator.trans('map.attribution.hint')} ${Translator.trans('map.attribution.placeholder')}`,
        text: Translator.trans('map.attribution')
      }"
      name="r_mapAttribution"
      v-model="mapAttribution" />

    <p class="weight--bold u-mb-0">
      {{ Translator.trans('map.base.settings.preview') }}:
    </p>

    <dp-ol-map
      ref="map"
      :key="`map_${mapKey}`"
      small
      :map-options="{
        baseLayer: baseLayerUrl,
        baseLayerLayers: baseLayerLayers,
        procedureScales: [920000,70000,15000],
        defaultMapExtent: mapExtent
      }"
      :options="{
        controls: [attributionControl],
        autoSuggest: { enabled: false },
        defaultAttribution: mapAttribution,
      }" />

    <dp-button-row
      class="u-mt"
      data-cy="customerSettingsMap"
      primary
      secondary
      :secondary-text="Translator.trans('reset')"
      @secondary-action="resetMapSettings"
      @primary-action="saveMapSettings" />
  </div>
</template>

<script>
import { debounce, DpButtonRow, DpInput } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { Attribution } from 'ol/control'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'

export default {
  name: 'CustomerSettingsMap',

  components: {
    DpButtonRow,
    DpInput,
    DpOlMap
  },

  props: {
    currentCustomerId: {
      required: true,
      type: String
    },

    initLayerUrl: {
      required: false,
      type: String,
      default: ''
    },

    initLayer: {
      required: false,
      type: String,
      default: ''
    },

    initMapAttribution: {
      required: false,
      type: String,
      default: ''
    },

    mapExtent: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      mapAttribution: this.initMapAttribution,
      baseLayerLayers: this.initLayer,
      baseLayerUrl: this.initLayerUrl,
      mapKey: 0
    }
  },

  computed: {
    ...mapState('Customer', {
      customerItems: 'items'
    }),

    attributionControl () {
      return new Attribution({ collapsible: false })
    }
  },

  methods: {
    ...mapActions('Customer', {
      saveCustomer: 'save'
    }),

    ...mapMutations('Customer', {
      updateCustomer: 'setItem'
    }),

    debounceUpdate: debounce(({ id, value }) => {
      if (id === 'r_baseLayerUrl' || id === 'r_baseLayerLayers') {
        if (id === 'r_baseLayerUrl') {
          this.baseLayerUrl = value
        } else {
          this.baseLayerLayers = value
        }
        this.mapKey = Math.floor(Math.random() * 1000)
      }
    }, 1000),

    resetMapSettings () {
      const previousState = this.customerItems[this.currentCustomerId].attributes
      const properties = ['mapAttribution', 'baseLayerLayers', 'baserLayerUrl']
      properties.forEach(prop => {
        this[prop] = previousState[prop]
      })
    },

    saveMapSettings () {
      const { baseLayerLayers, baseLayerUrl, mapAttribution } = this
      const payload = {
        id: this.currentCustomerId,
        type: 'Customer',
        attributes: {
          ...this.customerItems[this.currentCustomerId].attributes,
          baseLayerLayers,
          baseLayerUrl,
          mapAttribution
        }
      }
      this.updateCustomer(payload)
      this.saveCustomer(this.currentCustomerId).then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
      })
    }
  }
}
</script>
