<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="{ 'hidden': isMobile }">
    <div :class="prefixClass('c-map__group')">
      <button
        :class="[unfolded ? prefixClass('is-active') : '', prefixClass('c-map__group-header c-map__group-item c-map__toggle btn--blank o-link--default u-pv-0_25')]"
        data-cy="customLayer:layerUserDefined"
        @click="toggle">
        {{ Translator.trans('layer.userdefined') }}
      </button>
    </div>

    <form
      data-dp-validate="customLayer"
      :class="prefixClass('c-map__group c-map__group-item-color u-p-0_25')"
      v-show="unfolded">
      <layer-settings
        :available-projections="mappedAvailableProjections"
        :show-xplan-default-layer="false"
        ref="layerSettings" />
      <button
        @click="dpValidateAction('customLayer', emitAddLayer, false)"
        :class="prefixClass('btn btn--primary u-mb-0_25')"
        data-cy="customLayer:layerShow"
        type="button"
        id="addCustomLayer">
        {{ Translator.trans('layer.show') }}
      </button>
    </form>
  </div>
</template>

<script>
import { dpValidateMixin, prefixClass } from '@demos-europe/demosplan-ui'
import isMobile from 'ismobilejs'
import LayerSettings from '@DpJs/components/map/admin/LayerSettings'

export default {
  name: 'DpCustomLayer',

  components: {
    LayerSettings
  },

  mixins: [dpValidateMixin],

  props: {
    initAvailableProjections: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      isMobile: isMobile(window.navigator).any,
      unfolded: false
    }
  },

  computed: {
    mappedAvailableProjections () {
      return this.initAvailableProjections.map(el => ({ label: el.label, value: el.label }))
    }
  },

  methods: {
    emitAddLayer () {
      const { currentCapabilities, serviceType, url, name, layers, projection, matrixSet } = this.$refs.layerSettings

      if (currentCapabilities) {
        this.$root.$emit('addCustomlayer', { currentCapabilities, serviceType, url, name, layers, projection, tileMatrixSet: matrixSet })
      } else {
        return dplan.notify.error(Translator.trans('maplayer.capabilities.fetch.error'))
      }
    },

    toggle () {
      const unfolded = this.unfolded = !this.unfolded
      if (unfolded) {
        this.$root.$emit('custom-layer:unfolded')
      }
    },

    prefixClass (classList) {
      return prefixClass(classList)
    }
  },

  created () {
    this.$root.$on('layer-list:unfolded map-tools:unfolded layer-legend:unfolded', () => {
      this.unfolded = false
    })
  }
}
</script>
