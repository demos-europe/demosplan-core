<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-input
      id="r_name"
      v-model="name"
      class="u-mb-0_5"
      data-cy="newMapLayerName"
      :label="{
        text: Translator.trans('name')
      }"
      name="r_name"
      required />

    <dp-input
      id="r_url"
      v-model="url"
      class="u-mb-0_5"
      data-cy="newMapLayerURL"
      :label="{
        text: Translator.trans('url')
      }"
      name="r_url"
      required
      @blur="getLayerCapabilities"
      @enter="getLayerCapabilities" />

    <dp-select
      v-model="serviceType"
      class="u-mb-0_5"
      data-cy="layerSettings:serviceType"
      :label="{
        text: Translator.trans('type')
      }"
      name="r_serviceType"
      :options="serviceTypeOptions"
      required
      @select="setServiceInUrl" />
    <input
      type="hidden"
      name="r_serviceType"
      v-model="serviceType">

    <dp-checkbox
      v-if="hasPermission('feature_xplan_defaultlayers') && showXplanDefaultLayer"
      id="r_xplanDefaultlayers"
      class="u-mb-0_5"
      :label="{
        text: Translator.trans('explanation.gislayer.xplan.default')
      }"
      name="r_xplanDefaultlayers"
      style="display: none;"
      :title="Translator.trans('explanation.gislayer.default.defined') + ': ' + xplanDefaultLayer"
      value="1" />

    <dp-label
      :text="Translator.trans('layers')"
      for="r_layers"
      required />
    <dp-multiselect
      @input="filterMatrixSetByLayers"
      required
      track-by="label"
      label="label"
      multiple
      id="r_layers"
      v-model="layers"
      data-cy="newMapLayerLayers"
      :options="layersOptions"
      class="u-mb-0_5" />

    <input
      type="hidden"
      :value="layersInputValue"
      name="r_layers">
    <dp-select
      v-if="hasPermission('feature_map_wmts') && serviceType === 'wmts'"
      id="r_tileMatrixSet"
      v-model="matrixSet"
      class="u-mb-0_5"
      data-cy="layerSettings:matrixSet"
      :disabled="disabledMatrixSelect"
      :label="{
        text: Translator.trans('map.tilematrixset')
      }"
      name="r_tileMatrixSet"
      :options="matrixSetOptions"
      required
      @select="filterProjectionsByMatrixSet" />
    <input
      type="hidden"
      name="r_tileMatrixSet"
      v-model="matrixSet">

    <dp-select
      id="r_layerProjection"
      v-model="projection"
      class="u-mb-0_5"
      :disabled="disabledProjectionSelect"
      :label="{
        text: Translator.trans('projection')
      }"
      name="r_layerProjection"
      :options="projectionOptions"
      required />
    <input
      type="hidden"
      name="r_layerProjection"
      v-model="projection">

    <input
      type="hidden"
      name="r_layerVersion"
      v-model="version">
  </div>
</template>

<script>
import { debounce, DpCheckbox, DpInput, DpLabel, DpMultiselect, DpSelect, externalApi } from '@demos-europe/demosplan-ui'
import { WMSCapabilities, WMTSCapabilities } from 'ol/format'

export default {
  name: 'LayerSettings',

  components: {
    DpCheckbox,
    DpInput,
    DpLabel,
    DpMultiselect,
    DpSelect
  },

  props: {
    availableProjections: {
      type: Array,
      required: false,
      default: () => []
    },

    initLayers: {
      type: String,
      required: false,
      default: ''
    },

    initMatrixSet: {
      type: String,
      required: false,
      default: ''
    },

    initName: {
      type: String,
      required: false,
      default: ''
    },

    initProjection: {
      type: String,
      required: false,
      default: ''
    },

    initServiceType: {
      type: String,
      required: false,
      default: 'wms'
    },

    initUrl: {
      type: String,
      required: false,
      default: ''
    },

    initVersion: {
      type: String,
      required: false,
      default: ''
    },

    showXplanDefaultLayer: {
      type: Boolean,
      required: false,
      default: false
    },

    xplanDefaultLayer: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentCapabilities: null,
      /**
       * Required to determine if the selections should be dynamically set after getting the getCapabilities.
       * On pageLoad the settings from the saved layer should be used
       * Afterwards changing the URL will reset the layer- (matrixset-) selection,
       * though we can't tell if the new URL has the same sets of layers et al.
       */
      initialLoad: true,
      isLoading: true,
      layersOptions: [],
      layers: this.initLayers
        ? this.initLayers
          .split(',')
          .map(el => ({ label: el.trim(), value: el.trim() }))
        : [],
      matrixSet: this.initMatrixSet,
      matrixSetOptions: [],
      name: this.initName,
      projection: this.initProjection || window.dplan.defaultProjectionLabel,
      projectionOptions: this.availableProjections,
      serviceType: this.initServiceType || 'wms',
      url: this.initUrl,
      version: this.initVersion || '1.3.0'
    }
  },

  computed: {
    disabledLayerSelect () {
      return (!this.url ||
        !this.currentCapabilities ||
        this.isLoading ||
        this.layersOptions.length === 1)
    },

    disabledMatrixSelect () {
      return (!this.layersInputValue ||
        !this.currentCapabilities ||
        this.isLoading ||
        this.matrixSetOptions.length === 1)
    },

    disabledProjectionSelect () {
      return (!this.layersInputValue ||
        !this.currentCapabilities ||
        (this.serviceType === 'wmts' ? !this.matrixSet : false) ||
        this.isLoading ||
        this.projectionOptions.length === 1)
    },

    layersInputValue () {
      return this.layers.map(el => el.label).join(',')
    },

    serviceTypeOptions () {
      const serviceTypeOptions = [{ value: 'wms', label: 'WMS' }]
      if (hasPermission('feature_map_wmts')) {
        serviceTypeOptions.push({ value: 'wmts', label: 'WMTS' })
      }
      return serviceTypeOptions
    }
  },

  methods: {
    addLayerToOptions (layerArray, identifier) {
      layerArray.forEach(layer => {
        if (layer[identifier]) {
          this.layersOptions.push({ value: layer[identifier], label: layer[identifier] })
        }
        if (Array.isArray(layer.Layer)) {
          this.addLayerToOptions(layer.Layer, identifier)
        }
      })
    },

    extractDataFromWMSCapabilities () {
      // Show available layers in layers dropdown
      if (Array.isArray(this.currentCapabilities.Capability.Layer.Layer)) {
        this.addLayerToOptions(this.currentCapabilities.Capability.Layer.Layer, 'Name')
      } else if (this.currentCapabilities.Capability.Layer.Name) {
        this.layersOptions.push({ value: this.currentCapabilities.Capability.Layer, label: this.currentCapabilities.Capability.Layer.Name })
      } else {
        this.layersOptions = []
      }

      // Filter available projections by crs in capabilities
      if (this.currentCapabilities.Capability.Layer.CRS) {
        const availableCRS = this.currentCapabilities.Capability.Layer.CRS
        this.projectionOptions = this.projectionOptions.filter(el => availableCRS.includes(el.value))

        if (this.projectionOptions.length === 0) {
          dplan.notify.warning(Translator.trans('error.map.layer.projections.none', {
            projectionsFromSource: availableCRS.join(', '),
            availableProjectionsFromSystem: this.availableProjections.join(', ')
          }))
        } else if (this.findProjectionInOptions() === false) {
          this.projection = this.projectionOptions[0].value
        }
      }

      this.resetLayerSelection()
      if (this.initialLoad) {
        this.initialLoad = false
      }
    },

    extractDataFromWMTSCapabilities () {
      // Show available layers in layers dropdown
      if (Array.isArray(this.currentCapabilities.Contents.Layer)) {
        this.addLayerToOptions(this.currentCapabilities.Contents.Layer, 'Identifier')
      } else {
        this.layersOptions = []
      }

      this.filterMatrixSetByLayers()
      this.filterProjectionsByMatrixSet()
      this.resetLayerSelection()
      if (this.initialLoad) {
        this.initialLoad = false
      }
    },

    filterMatrixSetByLayers () {
      if (this.serviceType === 'wms') {
        return
      }
      this.$nextTick(() => {
        try {
          if (this.layers.length === 0) {
            this.matrixSet = ''
            this.projection = ''
            return
          }

          if (this.currentCapabilities === null) {
            dplan.notify.warning(Translator.trans('error.generic'))
            return
          }

          // Make array of arrays with supported tileMatrixSets for each selected layer
          const arrays = this.layers.map(layer => {
            const layerInCapabilities = this.currentCapabilities.Contents.Layer.find(l => l.Identifier === layer.label)
            return [...layerInCapabilities.TileMatrixSetLink.map(el => el.TileMatrixSet)]
          })

          // Find intersection of all arrays (find all matrixSets that are supported by ALL selected layers)
          const intersection = arrays.reduce((acc, curr) => acc.filter(el => curr.includes(el)))

          this.matrixSetOptions = intersection.map(el => {
            return { value: el, label: el }
          })

          if (this.matrixSetOptions.length === 0) {
            dplan.notify.warning(Translator.trans('maplayer.no.supported.matrix'))
          } else if (this.matrixSetOptions.length === 1) {
            if (this.initialLoad === false) {
              this.matrixSet = this.matrixSetOptions[0].label
            }
            this.filterProjectionsByMatrixSet()
          }
        } catch (err) {
          dplan.notify.warning(Translator.trans('error.generic'))
        }
      })
    },

    filterProjectionsByMatrixSet () {
      if (this.serviceType === 'wms') {
        return
      }

      this.$nextTick(() => {
        if (!this.matrixSet) {
          this.projection = ''
          return
        }

        const supportedCRSOfCurrentMatrixSet = this.currentCapabilities.Contents.TileMatrixSet.find(el => el.Identifier === this.matrixSet).SupportedCRS
        if (supportedCRSOfCurrentMatrixSet) {
          this.projectionOptions = this.availableProjections.filter(projection => projection.value === supportedCRSOfCurrentMatrixSet)
          if (this.projectionOptions.length > 0 || this.initialLoad === false) {
            this.projection = supportedCRSOfCurrentMatrixSet
          } else if (this.initialLoad === false) {
            dplan.notify.warning(Translator.trans('matrixset.no.supported.projections'))
          }
        }
      })
    },

    findProjectionInOptions () {
      return this.projectionOptions.some(obj => {
        return obj.value === this.projection
      })
    },

    getLayerCapabilities: debounce(function () {
      this.clearSelections()
      this.isLoading = true
      // Don't fetch anything if there is no url
      if (this.url === '') {
        return
      }

      const url = this.handleUrlParams(this.url)
      const hasWMTSType = url.toLowerCase().includes('wmts')
      let parser = null

      if (hasWMTSType && !hasPermission('feature_map_wmts')) {
        const urlInput = document.getElementById('r_url')
        urlInput.classList.add('is-invalid')

        return dplan.notify.notify('error', Translator.trans('maplayer.capabilities.invalid.type'))
      }

      externalApi(url)
        .then(response => {
          return response.text()
        })
        .then(capabilities => {
          this.serviceType = hasWMTSType ? 'wmts' : 'wms'
          parser = this.serviceType === 'wmts' ? new WMTSCapabilities() : new WMSCapabilities()
          this.currentCapabilities = parser.read(capabilities)

          if (this.currentCapabilities !== null) {
            this.version = this.currentCapabilities.version
            this.serviceType === 'wmts' ? this.extractDataFromWMTSCapabilities() : this.extractDataFromWMSCapabilities()
          }
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('maplayer.capabilities.fetch.error'))
          dplan.notify.notify('warning', Translator.trans('maplayer.capabilities.fetch.warning.cors.policy'))
          this.clearSelections()
        })
        .finally(() => {
          this.isLoading = false
        })
    }),

    handleUrlParams (url) {
      const upperUrl = url.toUpperCase()
      let separator = url.includes('?') ? '&' : '?'

      const serviceKey = 'SERVICE='
      if (upperUrl.includes(serviceKey) === false) {
        url = `${url}${separator}${serviceKey}${this.serviceType.toUpperCase()}`
        this.url = url
        separator = '&'
      } else {
        const serviceMatch = upperUrl.match(new RegExp(serviceKey + '(\\w*)', 'i'))[1]
        if (serviceMatch !== 'WMS' && serviceMatch !== 'WMTS') {
          dplan.notify.warning(Translator.trans('error.map.layer.service.unknown', { type: serviceMatch }))
        }
      }

      const getCapParam = 'REQUEST=GetCapabilities'
      if (upperUrl.includes(getCapParam) === false) {
        url = `${url}${separator}${getCapParam}`
      }

      return url
    },

    clearSelections () {
      this.currentCapabilities = null
      this.layersOptions = []
      this.matrixSetOptions = []
      this.projectionOptions = this.availableProjections
    },

    resetLayerSelection () {
      if (this.initialLoad) {
        return
      }
      // After changing the URL, the fetched Layer won't match the previous selection
      if (this.layersOptions.length === 1) {
        // If there is just one option available, select it by default
        this.layers = this.layersOptions
      } else {
        // Otherwise reset selection to empty
        this.layers = []
      }
    },

    setServiceInUrl () {
      const serviceKey = 'SERVICE='
      // Find existing Key
      const serviceParam = new RegExp(serviceKey + '(\\w*)', 'i')
      if (this.url.match(serviceParam).length > 0) {
        this.url = this.url.replace(serviceParam, `${serviceKey}${this.serviceType.toUpperCase()}`)
      } else {
        const separator = this.url.includes('?') ? '&' : '?'
        this.url = `${this.url}${separator}${serviceKey}${this.serviceType.toUpperCase()}`
      }

      this.getLayerCapabilities()
    }
  },

  mounted () {
    this.getLayerCapabilities()
  }
}
</script>
