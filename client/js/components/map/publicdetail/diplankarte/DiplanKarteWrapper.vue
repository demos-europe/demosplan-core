<template>
  <div class="h-full w-full">
    <dp-button
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
      :customLayerGroupName.prop="customLayerGroupName"
      profile="beteiligung"
      enable-layer-switcher
      enable-searchbar
      enable-toolbar
      @diplan-karte:geojson-update="handleDrawing"
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
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
import { DpButton, DpNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'
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

const buildLayerConfigsList = () => {
  const layersFromDB = store.getters['Layers/elementListForLayerSidebar'](null, 'overlay', true)

  return layersFromDB
    .map((layer) => {
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
    return createLayerObject(config.baseConfig, config.specificConfig)
  })
}

const closeLocationInfo = () => {
  isLocationInfoClosed.value = true
}

const createLayerObject = (baseConfig, specificConfig = {}) => {
  if (!baseConfig || Object.keys(baseConfig).length < 4) {
    return {}
  }

  const { id, name, type, url } = baseConfig
  const baseLayer = {
    id,
    name,
    typ: type,
    url,
  }

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

  if (!layerTypeDefaults[type.toLowerCase()]) {
    return {}
  }

  const mergedConfig = { ...layerTypeDefaults[type.toLowerCase()], ...specificConfig }

  return {
    ...baseLayer,
    ...mergedConfig,
  }
}

const customLayerConfigurationList = ref([])

const customLayerGroupName = Translator.trans('gislayer')

const customLayerList = ref([])

const drawing = computed(() => {
  return initDrawing ?
    transformFeatureCollection(JSON.parse(initDrawing), 'EPSG:3857', 'EPSG:4326') :
    ''
})

const emit = defineEmits(['locationDrawing'])

const handleDrawing = (event) => {
  let payload
  const geometry = transformFeatureCollection(event.detail[0], 'EPSG:4326', 'EPSG:3857')

  // If all geometry was deleted, reset location reference
  if (geometry.features.length === 0) {
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
  }

  emit('locationDrawing', payload)
}

const instance = getCurrentInstance()

const isLocationInfoClosed = ref(false)

const isLocationToolSelected = computed(() => {
  return store.state.PublicStatement.activeActionBoxTab === 'draw'
})

const isStoreAvailable = computed(() => {
  return store.state.PublicStatement.storeInitialised
})

const layerConfigBuilders = {
  wms: (layer) => ({
    layers: layer.attributes.layers || null,
    version: layer.attributes.layerVersion || '1.3.0',
  }),
  // Add other type specific values that could come from BE here
}

const layersLoaded = ref(false)

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

const store = useStore()

const toggleStatementModal = (updateStatementPayload) => {
  instance.parent.refs.statementModal.toggleModal(true, updateStatementPayload)
}

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

instance.appContext.app.mixin(prefixClassMixin)

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

</script>
