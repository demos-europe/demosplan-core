<template>
  <div class="h-full w-full">
    <dp-button
      v-if="layersLoaded"
      id="statementModalButton"
      :class="prefixClass('left-[365px] top-[24px] pt-[11px] pb-[11px] pl-[20px] pr-[20px] !absolute z-above-zero')"
      :text="activeStatement ? Translator.trans('statement.participate.resume') : Translator.trans('statement.participate')"
      data-cy="publicStatementButton"
      rounded
      @click="openStatementModalOrLoginPage"
    />

    <dp-notification
      v-if="isLocationToolSelected && !isLocationInfoClosed"
      id="locationReferenceInfo"
      :message="{ text: Translator.trans('statement.map.draw.mark_place_notification'), type: 'warning' }"
      :class="prefixClass('left-[565px] top-[5px] w-auto !absolute z-above-zero')"
      @dp-notify-remove="closeLocationInfo"
    />

    <diplan-karte
      v-if="isStoreAvailable && layersLoaded"
      :fitToExtent.prop="transformedInitialExtent"
      :geltungsbereich.prop="transformedTerritory"
      :geojson="drawing"
      :layerConfig.prop="layerConfig"
      :portalConfig.prop="portalConfig"
      :customLayerList.prop="customLayerList"
      :customLayerConfigurationList.prop="customLayerConfigurationList"
      :customLayerGroupName.prop="Translator.trans('gislayer')"
      profile="beteiligung"
      enable-layer-switcher
      enable-searchbar
      enable-toolbar
      @diplan-karte:geojson-update="handleDrawing"
    />

    <dp-loading
      v-else-if="!layersLoaded"
      overlay
    />

    <div
      v-if="copyright"
      :class="prefixClass('left-0 bottom-[10px] !absolute z-above-zero bg-white/80 px-1 py-0.5 text-xs text-gray-600 rounded max-w-2/3')"
    >
      {{ copyright }}
    </div>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref, watch } from 'vue'
import { DpButton, DpLoading, DpNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { transformExtent, transformFeatureCollection } from '@DpJs/lib/map/transformFeature'
import layerConfig from './config/layerConfig.json'
import portalConfig from './config/portalConfig.json'
import { registerWebComponent } from '@init/diplan-karten'
import { useStore } from 'vuex'

const { activeStatement, copyright, initDrawing, initialExtent, loginPath, styleNonce, territory } = defineProps({
  activeStatement: {
    type: Boolean,
    required: true,
  },

  copyright: {
    type: String,
    required: false,
    default: '',
  },

  initDrawing: {
    type: Object,
    required: false,
    default: () => ({
      type: 'FeatureCollection',
      features: [],
    }),
  },

  initialExtent: {
    type: Array,
    required: false,
    default: () => [],
  },

  loginPath: {
    type: String,
    required: true,
  },

  styleNonce: {
    type: String,
    required: true,
  },

  territory: {
    type: Object,
    required: false,
    default: () => ({
      type: 'FeatureCollection',
      features: [],
    }),
  },
})

const emit = defineEmits(['locationDrawing'])

const instance = getCurrentInstance()

const store = useStore()

instance.appContext.app.mixin(prefixClassMixin)

const isStoreAvailable = computed(() => {
  return store.state.PublicStatement.storeInitialised
})

// Feature: Drawing on Map

const drawing = computed(() => {
  return initDrawing ?
    transformFeatureCollection(JSON.parse(initDrawing), 'EPSG:3857', 'EPSG:4326') :
    ''
})

// Track previous feature count to detect additions vs deletions
const previousFeatureCount = ref(0)

const handleDrawing = (event) => {
  let payload
  const geometry = transformFeatureCollection(event.detail[0], 'EPSG:4326', 'EPSG:3857')
  const currentFeatureCount = geometry.features.length

  // If all geometry was deleted, reset location reference
  if (currentFeatureCount === 0) {
    payload = {
      r_location: 'notLocated',
      r_location_geometry: '',
      r_location_point: '',
      location_is_set: '',
    }
  } else {
    payload = {
      r_location: 'point',
      r_location_geometry: JSON.stringify(geometry),
      r_location_priority_area_key: '',
      r_location_priority_area_type: '',
      r_location_point: '',
      location_is_set: 'geometry',
    }

    // Only open modal when features are added/edited, not when deleted
    if (currentFeatureCount >= previousFeatureCount.value) {
      toggleStatementModal()
    }
  }

  previousFeatureCount.value = currentFeatureCount

  emit('locationDrawing', payload)
}

// Feature: Set location info

const isLocationInfoClosed = ref(false)

const closeLocationInfo = () => {
  isLocationInfoClosed.value = true
}

const isLocationToolSelected = computed(() => {
  return store.state.PublicStatement.activeActionBoxTab === 'draw'
})

// Feature: Show Overlays/Layers in Layer Switcher and on Map

const customLayerConfigurationList = ref([])

const customLayerList = ref([])

const layersLoaded = ref(false)

const layerTypeDefaults = {
  // Add more defaults for other types here if needed
  wms: {
    layers: '',
    format: 'image/png',
    version: '1.3.0',
    singleTile: false,
    transparent: true,
    transparency: 0,
    gutter: 0,
    minScale: '0',
    maxScale: '2500000',
    tilesize: 512,
    visibleOnLoad: false,
  },
}

const layerConfigBuilders = {
  wms: (layer) => ({
    layers: layer.attributes.layers || null,
    version: layer.attributes.layerVersion || '1.3.0',
  }),
  // Add other type specific values that could come from BE here
}

const buildLayerConfigsList = () => {
  const layersFromDB = store.getters['Layers/elementListForLayerSidebar'](null, 'overlay', true)

  return layersFromDB
    .map(layer => {
      const layerType = layer.attributes.serviceType?.toLowerCase()
      const configBuilder = layerConfigBuilders[layerType]

      if (!configBuilder) {
        console.warn(`No config builder found for layer type: ${layerType}`)

        return null
      }

      return {
        baseConfig: {
          id: layer.id,
          name: layer.attributes.name,
          type: layerType,
          url: layer.attributes.url,
        },
        specificConfig: configBuilder(layer),
      }
    })
    .filter(Boolean) // Deletes null-Values for unknown Layer-Types
}

const buildLayerList = (layerConfigs) => {
  return layerConfigs.map(config => {
    return createLayerObject(config.baseConfig, config.specificConfig, layerTypeDefaults)
  })
}

const createLayerObject = (baseConfig, specificConfig = {}, layerTypeDefaults = {}) => {
  const requiredKeys = ['id', 'name', 'type', 'url']
  const baseConfigHasNeededKeys = baseConfig && requiredKeys.every(key => baseConfig?.hasOwnProperty(key))

  if (!baseConfigHasNeededKeys) {
    return {}
  }

  const { id, name, type, url } = baseConfig
  const baseLayer = {
    id,
    name,
    typ: type,
    url,
  }

  if (!layerTypeDefaults[type.toLowerCase()]) {
    return {}
  }

  const mergedConfig = { ...layerTypeDefaults[type.toLowerCase()], ...specificConfig }

  return {
    ...baseLayer,
    ...mergedConfig,
  }
}

const updateCustomLayerData = () => {
  if (isStoreAvailable.value) {
    const layerConfigs = buildLayerConfigsList()
    const layerList = buildLayerList(layerConfigs)

    customLayerList.value = layerList
    customLayerConfigurationList.value = layerList.map(layer => ({
      Titel: layer.name,
      Layer: [{ id: layer.id }],
    }))

    layersLoaded.value = true
  }
}

// Feature: Statement Modal

const openStatementModalOrLoginPage = (event) => {
  if (!hasPermission('feature_new_statement')) {
    window.location.href = loginPath

    return
  }

  isLocationInfoClosed.value = false

  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })

  event.preventDefault()
  event.stopPropagation()
  toggleStatementModal({})
}

const toggleStatementModal = (updateStatementPayload) => {
  instance.parent.refs.statementModal.toggleModal(true, updateStatementPayload)
}

// Feature: Territory and Extent

const transformedInitialExtent = ref([])

const transformedTerritory = reactive({
  type: 'FeatureCollection',
  features: [],
})

const transformInitialExtent = () => {
  if (!initialExtent || initialExtent.length === 0) {
    transformedInitialExtent.value = undefined
  } else {
    transformedInitialExtent.value = transformExtent(initialExtent, 'EPSG:3857', 'EPSG:4326')
  }
}

const transformTerritoryCoordinates = () => {
  if (!territory || !territory.features || territory.features.length === 0) {
    transformedTerritory.type = 'FeatureCollection'
    transformedTerritory.features = []
    return
  }

  const transformed = transformFeatureCollection(territory, 'EPSG:3857', 'EPSG:4326')
  transformedTerritory.type = transformed.type
  transformedTerritory.features = transformed.features
}

// Hooks

onMounted(() => {
  store.dispatch('Layers/get', { procedureId: store.state.PublicStatement.procedureId })
    .then(() => {
      updateCustomLayerData()
    })

  registerWebComponent({
    nonce: styleNonce,
  })

  // Transform data once on mount
  transformInitialExtent()
  transformTerritoryCoordinates()

  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })
})

// Initialize previousFeatureCount based on the features-array length in the drawing-computed
watch(
  drawing,
  (val) => {
    if (val && typeof val === 'object' && Array.isArray(val.features) && val.features.length > 0) {
      previousFeatureCount.value = val.features.length
    }
  },
  { immediate: true },
)

</script>
