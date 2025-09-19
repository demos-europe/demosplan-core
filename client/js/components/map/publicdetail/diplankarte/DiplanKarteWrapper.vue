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
      v-if="isStoreAvailable"
      :fitToExtent.prop="transformedInitialExtent"
      :geltungsbereich.prop="transformedTerritory"
      :geojson="drawing"
      :layerConfig.prop="layerConfig"
      :portalConfig.prop="portalConfig"
      profile="beteiligung"
      enable-layer-switcher
      enable-searchbar
      enable-toolbar
      @diplan-karte:geojson-update="handleDrawing"
    />

    <div
      v-if="copyright"
      :class="prefixClass('left-0 bottom-[10px] !absolute z-above-zero bg-white/80 px-1 py-0.5 text-xs text-gray-600 rounded max-w-xs')"
      v-html="copyright"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
import { DpButton, DpNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { registerWebComponent } from '@init/diplan-karten'
import { transformFeatureCollection } from '@DpJs/lib/map/transformFeature'
import { useStore } from 'vuex'
import portalConfig from './config/portalConfig.json'
import layerConfig from './config/layerConfig.json'

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

const transformedInitialExtent = ref([])

const transformedTerritory = reactive({
  type: 'FeatureCollection',
  features: [],
})

const drawing = computed(() => {
  return initDrawing ?
    transformFeatureCollection(JSON.parse(initDrawing), 'EPSG:3857', 'EPSG:4326') :
    ''
})

const transformInitialExtent = () => {
  if (!initialExtent || initialExtent.length !== 4) {
    transformedInitialExtent.value = []
    return
  }

  // Create a temporary FeatureCollection with a bounding box polygon
  const [minX, minY, maxX, maxY] = initialExtent
  const tempFeatureCollection = {
    type: 'FeatureCollection',
    features: [{
      type: 'Feature',
      geometry: {
        type: 'Polygon',
        coordinates: [[
          [minX, minY],
          [maxX, minY],
          [maxX, maxY],
          [minX, maxY],
          [minX, minY]
        ]]
      }
    }]
  }

  const transformed = transformFeatureCollection(tempFeatureCollection, 'EPSG:3857', 'EPSG:4326')

  if (!transformed.features || transformed.features.length === 0) {
    transformedInitialExtent.value = []
    return
  }

  // Extract bounds from transformed coordinates
  const coords = transformed.features[0].geometry.coordinates[0]
  const longitudes = coords.map(coordinate => coordinate[0])
  const latitudes = coords.map(coordinate => coordinate[1])

  transformedInitialExtent.value = [
    Math.min(...longitudes),
    Math.min(...latitudes),
    Math.max(...longitudes),
    Math.max(...latitudes),
  ]
}

const transformTerritoryCoordinates = () => {
  if (!territory || !territory.features || territory.features.length === 0) {
    Object.assign(transformedTerritory, {
      type: 'FeatureCollection',
      features: [],
    })
    return
  }

  const transformed = transformFeatureCollection(territory, 'EPSG:3857', 'EPSG:4326')
  Object.assign(transformedTerritory, transformed)
}

const emit = defineEmits(['locationDrawing'])

const instance = getCurrentInstance()
const store = useStore()

instance.appContext.app.mixin(prefixClassMixin)

const isStoreAvailable = computed(() => {
  return store.state.PublicStatement.storeInitialised
})

const isLocationInfoClosed = ref(false)

const isLocationToolSelected = computed(() => {
  return store.state.PublicStatement.activeActionBoxTab === 'draw'
})

const closeLocationInfo = () => {
  isLocationInfoClosed.value = true
}

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

onMounted(() => {
  registerWebComponent({
    nonce: styleNonce,
  })

  // Transform data once on mount
  transformInitialExtent()
  transformTerritoryCoordinates()

  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })
})

</script>
