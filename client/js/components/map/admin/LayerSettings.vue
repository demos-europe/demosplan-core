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
      :label="{
        text: Translator.trans('name')
      }"
      class="u-mb-0_5"
      data-cy="newMapLayerName"
      name="r_name"
      required
    />

    <dp-select
      v-model="serviceType"
      :label="{
        text: Translator.trans('type')
      }"
      :options="serviceTypeOptions"
      class="u-mb-0_5"
      data-cy="layerSettings:serviceType"
      name="r_serviceType"
      required
      @select="handleServiceTypeSelection"
    />

    <input
      :value="serviceType"
      name="r_serviceType"
      type="hidden"
    >

    <dp-input
      id="r_url"
      v-model="url"
      :label="{
        text: Translator.trans('url')
      }"
      class="u-mb-0_5"
      data-cy="newMapLayerURL"
      name="r_url"
      required
      @blur="validateUrlAndGetCapabilities"
      @enter="validateUrlAndGetCapabilities"
    />

    <dp-checkbox
      v-if="hasPermission('feature_xplan_defaultlayers') && showXplanDefaultLayer"
      id="r_xplanDefaultlayers"
      :label="{
        text: Translator.trans('explanation.gislayer.xplan.default')
      }"
      :title="Translator.trans('explanation.gislayer.default.defined') + ': ' + xplanDefaultLayer"
      class="u-mb-0_5"
      name="r_xplanDefaultlayers"
      style="display: none;"
      value="1"
    />

    <dp-label
      v-if="serviceType === 'wms' || serviceType === 'wmts'"
      :text="Translator.trans('layers')"
      class="mb-0.5"
      for="r_layers"
      required
    />

    <dp-multiselect
      v-if="serviceType === 'wms' || serviceType === 'wmts'"
      id="r_layers"
      v-model="layers"
      :options="layersOptions"
      class="u-mb-0_5"
      data-cy="newMapLayerLayers"
      label="label"
      track-by="label"
      multiple
      required
      selection-controls
      @input="filterMatrixSetByLayers"
      @select-all="selectAllLayers"
      @deselect-all="deselectAllLayers"
    >
      <template v-slot:tag="{ props }">
        <span class="multiselect__tag">
          {{ props.option.label }}
          <dp-contextual-help
            v-if="unavailableLayers.includes(props.option.label) || serviceError"
            :class="prefixClass('ml-1 mb-0.5 text-message-severe')"
            :text="serviceError ? Translator.trans('map.service.unreachable') : Translator.trans('layer.unavailable')"
            icon="warning-circle"
          />

          <button
            class="multiselect__tag-icon"
            type="button"
            @click="props.remove(props.option)"
          />
        </span>
      </template>
    </dp-multiselect>

    <input
      :value="layersInputValue"
      name="r_layers"
      type="hidden"
    >

    <dp-ol-map
      v-if="hasPermission('feature_map_layer_preview') && hasPreview && serviceType !== 'OAF'"
      :layers="previewLayers"
      :procedure-id="procedureId"
      small
    />

    <dp-select
      v-if="hasPermission('feature_map_wmts') && serviceType === 'wmts'"
      id="r_tileMatrixSet"
      v-model="matrixSet"
      :disabled="disabledMatrixSelect"
      :label="{
        text: Translator.trans('map.tilematrixset')
      }"
      :options="matrixSetOptions"
      class="u-mb-0_5"
      data-cy="layerSettings:matrixSet"
      name="r_tileMatrixSet"
      required
      @select="filterProjectionsByMatrixSet"
    />

    <input
      v-model="matrixSet"
      name="r_tileMatrixSet"
      type="hidden"
    >

    <dp-select
      id="r_layerProjection"
      v-model="projection"
      :disabled="disabledProjectionSelect"
      :label="{
        text: Translator.trans('projection')
      }"
      :options="projectionOptions"
      class="u-mb-0_5"
      name="r_layerProjection"
      required
    />

    <input
      v-model="projection"
      name="r_layerProjection"
      type="hidden"
    >

    <input
      v-if="serviceType === 'OAF' && projectionOgcUri"
      v-model="projectionOgcUri"
      name="r_layerProjectionOgcUri"
      type="hidden"
    >

    <input
      v-model="version"
      name="r_layerVersion"
      type="hidden"
    >
  </div>
</template>

<script>
import { debounce, DpCheckbox, DpContextualHelp, DpInput, DpLabel, DpMultiselect, DpSelect, externalApi, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { WMSCapabilities, WMTSCapabilities } from 'ol/format'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'LayerSettings',

  components: {
    DpOlMap: defineAsyncComponent(() => import('../map/DpOlMap')),
    DpCheckbox,
    DpContextualHelp,
    DpInput,
    DpLabel,
    DpMultiselect,
    DpSelect,
  },

  mixins: [prefixClassMixin],

  props: {
    availableProjections: {
      type: Array,
      required: false,
      default: () => [],
    },

    hasPreview: {
      type: Boolean,
      required: false,
      default: false,
    },

    initLayers: {
      type: String,
      required: false,
      default: '',
    },

    initMatrixSet: {
      type: String,
      required: false,
      default: '',
    },

    initName: {
      type: String,
      required: false,
      default: '',
    },

    initProjection: {
      type: String,
      required: false,
      default: '',
    },

    initServiceType: {
      type: String,
      required: false,
      default: 'wms',
    },

    initUrl: {
      type: String,
      required: false,
      default: '',
    },

    initVersion: {
      type: String,
      required: false,
      default: '',
    },

    procedureId: {
      type: String,
      required: false,
      default: '',
    },

    showXplanDefaultLayer: {
      type: Boolean,
      required: false,
      default: false,
    },

    xplanDefaultLayer: {
      type: String,
      required: false,
      default: '',
    },
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
      layers: this.initLayers ?
        this.initLayers
          .split(',')
          .map(el => ({ label: el.trim(), value: el.trim() })) :
        [],
      matrixSet: this.initMatrixSet,
      matrixSetOptions: [],
      name: this.initName,
      projection: this.initProjection || window.dplan.defaultProjectionLabel,
      projectionOgcUri: '',
      projectionOptions: this.availableProjections,
      serviceError: false,
      serviceType: this.initServiceType || 'wms',
      unavailableLayers: [],
      url: this.initUrl,
      version: this.initVersion || '1.3.0',
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

    previewLayers () {
      return [{
        name: `preview-layers-${this.layersInputValue}`, // Force component recreation on layer change (DpOlMapLayer)
        url: this.url,
        layers: this.layersInputValue,
        mapOrder: 1,
        layerType: 'overlay',
      }]
    },

    serviceTypeOptions () {
      const serviceTypeOptions = [{ value: 'wms', label: 'WMS' }]
      if (hasPermission('feature_map_wmts')) {
        serviceTypeOptions.push({ value: 'wmts', label: 'WMTS' })
      }

      if (hasPermission('feature_map_oaf')) {
        serviceTypeOptions.push({ value: 'OAF', label: 'OGC API – Features (OAF)' })
      }
      return serviceTypeOptions
    },
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

    clearSelections () {
      this.currentCapabilities = null
      this.layersOptions = []
      this.matrixSetOptions = []
      this.projectionOptions = this.availableProjections
    },

    /**
     * Convert OGC URI format to EPSG label
     * @param {string} ogcUri - e.g., "http://www.opengis.net/def/crs/EPSG/0/25832"
     * @returns {string} - e.g., "EPSG:25832"
     */
    convertOgcUriToEpsgLabel (ogcUri) {
      // Handle EPSG URIs
      const epsgMatch = ogcUri.match(/\/EPSG\/\d+\/(\d+)$/i)
      if (epsgMatch) {
        return `EPSG:${epsgMatch[1]}`
      }

      // Handle CRS84 (maps to WGS84)
      if (ogcUri.includes('CRS84')) {
        return 'EPSG:4326'
      }

      // Return original if can't parse
      return ogcUri
    },

    deselectAllLayers () {
      this.layers = []
    },

    /**
     * Extract collection name from OAF URL
     * @param {string} url - Full OAF URL
     * @returns {string} - Collection name
     */
    extractCollectionNameFromUrl (url) {
      const startIndex = url.indexOf('/collections/') + '/collections/'.length
      let endIndex = url.length

      const nextSlashIndex = url.indexOf('/', startIndex)
      const nextQueryIndex = url.indexOf('?', startIndex)

      if (nextSlashIndex !== -1) {
        endIndex = nextSlashIndex
      }
      if (nextQueryIndex !== -1 && nextQueryIndex < endIndex) {
        endIndex = nextQueryIndex
      }

      return url.substring(startIndex, endIndex)
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
            availableProjectionsFromSystem: this.availableProjections.join(', '),
          }))
        } else if (this.findProjectionInOptions() === false) {
          this.projection = this.projectionOptions[0].value
        }
      }

      this.resetLayerSelection()
      this.validateSavedLayersAvailability()

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
      this.validateSavedLayersAvailability()

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
          this.serviceError = false
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
          this.serviceError = true
        })
        .finally(() => {
          this.isLoading = false
        })
    }),

    /**
     * Fetch OAF collection metadata and auto-detect projection
     */
    getOafProjection () {
      if (!this.url || this.url === '') {
        return
      }

      try {
        const collectionName = this.extractCollectionNameFromUrl(this.url)
        const baseUrl = this.url.substring(0, this.url.indexOf('/collections/'))
        const metadataUrl = `${baseUrl}/collections/${collectionName}`

        this.isLoading = true

        externalApi(metadataUrl)
          .then(response => response.json())
          .then(data => {
            // Store OGC URI from metadata
            this.projectionOgcUri = data.storageCrs
            // Set dropdown to matching EPSG label
            this.setProjectionFromOgcUri(data.storageCrs)
          })
          .catch(error => {
            console.error('OAF metadata fetch error:', error)
            dplan.notify.error(Translator.trans('error.map.layer.oaf.metadata.fetch'))
          })
          .finally(() => {
            this.isLoading = false
          })
      } catch (error) {
        console.error('Error parsing OAF URL:', error)
        this.isLoading = false
      }
    },

    handleServiceTypeSelection () {
      if (this.serviceType === 'OAF') {
        return
      }

      this.getLayerCapabilities()
    },

    handleUrlParams (url) {
      const upperUrl = url.toUpperCase()
      let separator = url.includes('?') ? '&' : '?'

      const serviceKey = 'SERVICE='
      if (upperUrl.includes(serviceKey) === false) {
        url = `${url}${separator}${serviceKey}${this.serviceType.toUpperCase()}`
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

    selectAllLayers () {
      this.layers = [...this.layersOptions]
    },

    /**
     * Set projection field based on OGC URI from metadata
     * @param {string} ogcUri - OGC URI format from storageCrs
     */
    setProjectionFromOgcUri (ogcUri) {
      const epsgLabel = this.convertOgcUriToEpsgLabel(ogcUri)
      this.projection = epsgLabel
      dplan.notify.confirm(Translator.trans('map.layer.oaf.projection.detected', { projection: epsgLabel }))
    },

    validateOafUrl () {
      /*
       * With the current frontend architecture, it's not possible to validate the url on submit,
       * because the submit button is in a twig file (map_admin_gislayer_edit.html.twig).
       * So here we give UX feedback via notifications only - backend does authoritative validation that prevents the save.
       */
      if (!this.url || this.url === '') {
        return true
      }

      const collectionsPattern = '/collections/'
      const lowerUrl = this.url.toLowerCase()
      const collectionsIndex = lowerUrl.indexOf(collectionsPattern)

      // Check if URL contains /collections/ (case-insensitive)
      if (collectionsIndex === -1) {
        const errorMessage = Translator.trans('error.map.layer.oaf.missing.collections')
        dplan.notify.error(errorMessage)

        return false
      }

      // Check if /collections/ is not at the end (there must be content after it)
      const afterCollections = this.url.substring(collectionsIndex + collectionsPattern.length)
      const hasNoCollectionName = afterCollections.trim() === '' || afterCollections === '/' || afterCollections.match(/^\/+$/)

      if (hasNoCollectionName) {
        const errorMessage = Translator.trans('error.map.layer.oaf.collections.end')
        dplan.notify.error(errorMessage)

        return false
      }

      return true
    },

    validateSavedLayersAvailability () {
      if (this.layers.length === 0) {
        return
      }

      const savedLayers = this.layers.map(layer => layer.label)

      if (this.layersOptions.length === 0) {
        this.unavailableLayers = savedLayers

        return
      }

      const availableLayerOptions = this.layersOptions.map(option => option.label)
      const outdatedLayers = savedLayers.filter(savedName => !availableLayerOptions.includes(savedName))

      if (outdatedLayers.length > 0) {
        this.unavailableLayers = outdatedLayers
      } else {
        this.unavailableLayers = []
      }
    },

    validateUrlAndGetCapabilities () {
      if (this.serviceType === 'OAF') {
        if (!this.validateOafUrl()) {
          return
        }

        this.getOafProjection()
        return
      }

      if (!this.validateWmsWmtsUrl()) {
        return
      }

      this.getLayerCapabilities()
    },

    validateWmsWmtsUrl () {
      // UX feedback via notifications only - backend does authoritative validation
      if (!this.url || this.url === '') {
        return true
      }

      const upperUrl = this.url.toUpperCase()

      // Check if URL contains SERVICE parameter
      if (!upperUrl.includes('SERVICE=')) {
        const errorMessage = Translator.trans('error.map.layer.missing.service')
        dplan.notify.error(errorMessage)

        return false
      }

      return true
    },
  },

  mounted () {
    this.validateUrlAndGetCapabilities()
  },
}
</script>
