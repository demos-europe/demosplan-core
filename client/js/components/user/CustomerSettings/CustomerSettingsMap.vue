<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout u-pl">
    <dp-input
      id="r_baseLayerUrl"
      v-model="layerUrl"
      class="u-mb-0_75"
      :label="{
        hint: Translator.trans('map.base.url.hint'),
        text: Translator.trans('map.base.url')
      }"
      name="r_baseLayerUrl"
      @input="debounceUpdate" />

    <dp-input
      id="r_baseLayerLayers"
      v-model="layer"
      class="u-mb-0_75"
      :label="{
        hint: Translator.trans('map.layer.name.hint'),
        text: Translator.trans('layers')
      }"
      name="r_baseLayerLayers"
      @input="debounceUpdate" />

    <dp-input
      id="r_mapAttribution"
      class="u-mb-0_75"
      :label="{
        hint: Translator.trans('map.attribution.hint'),
        text: Translator.trans('map.attribution')
      }"
      name="r_mapAttribution"
      :value="mapAttribution" />

    <p class="weight--bold u-mb-0">
      {{ Translator.trans('map.base.settings.preview') }}:
    </p>

    <dp-ol-map
      ref="map"
      :key="`map_${mapKey}`"
      small
      :map-options="{
        baseLayer: layerUrl,
        baseLayerLayers: layer,
        procedureScales: [920000,70000,15000],
        defaultMapExtent: mapExtent
      }"
      :options="{
        controls: [],
        autoSuggest: { enabled: false },
      }" />
  </div>
</template>

<script>
import { debounce, DpInput } from '@demos-europe/demosplan-ui'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'

export default {
  name: 'CustomerSettingsMap',

  components: {
    DpInput,
    DpOlMap
  },

  props: {
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

    mapAttribution: {
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
      layer: this.initLayer,
      layerUrl: this.initLayerUrl,
      mapKey: 0
    }
  },

  methods: {
    debounceUpdate: debounce(({ id, value }) => {
      if (id === 'r_baseLayerUrl' || id === 'r_baseLayerLayers') {
        if (id === 'r_baseLayerUrl') {
          this.layerUrl = value
        } else if (id === 'r_baseLayerLayers') {
          this.layer = value
        }
        this.mapKey = Math.floor(Math.random() * 1000)
      }
    }, 1000)
  }
}
</script>
