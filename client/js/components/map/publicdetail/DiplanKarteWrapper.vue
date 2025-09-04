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
      v-if="portalConfig && layerConfig"
      :fit-to-extent="extent"
      :portal-config="portalConfig"
      :layer-config="layerConfig"
      :jwt-access-token="authToken"
      enable-searchbar
      enabled-toolbar-group="none"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'
import { DpButton, DpNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { registerWebComponent } from '@init/diplan-karten'
import { transformFeatureCollection } from '@DpJs/lib/map/transformFeature'
import { useStore } from 'vuex'

const { activeStatement, initDrawing, loginPath, styleNonce } = defineProps({
  activeStatement: {
    type: Boolean,
    required: true,
  },

  initDrawing: {
    type: Object,
    required: false,
    default: () => ({
      type: 'FeatureCollection',
      features: [],
    }),
  },

  loginPath: {
    type: String,
    required: true,
  },

  styleNonce: {
    type: String,
    required: true,
  },
})

// From readme diplankarte

const api = 'https://geodienste.develop.diplanung.de/api/karte-backend'
const authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3NTY5MDIxMTcsImV4cCI6MTc1NzE2MTMxNywicm9sZXMiOlsiUkNJVElaIl0sInVzZXJuYW1lIjoiYm9iLXNoNDNAZGVtb3MtZGV1dHNjaGxhbmQuZGUifQ.lGQC5ID_Xxkk8_P2RJq-EdtetbWTw22W1Udg0exJ0xE'

const portalConfig = ref()
const layerConfig = ref()

/**
 * BEGIN helper functions you could also move to a helper file
 */
function getHeaders (authToken) {
  if (!authToken) {
    throw new Error('There was no jwtAuthToken set!')
  }
  return {
    Authorization: `Bearer ${authToken}`,
  }
}

async function getLayerConfig (authToken) {
  const headers = getHeaders(authToken)
  const response = await fetch(`${api}/config/layer`, {
    method: 'GET',
    headers: headers,
  })

  if (!response.ok) {
    throw new Error(
      `Failed to fetch layerConfig: ${response.status} ${response.statusText}`,
    )
  }

  return await response.json()
}

async function getPortalConfig (authToken) {
  const headers = getHeaders(authToken)
  const response = await fetch(`${api}/config/portal`, {
    method: 'GET',
    headers: headers,
  })

  if (!response.ok) {
    throw new Error(
      `Failed to fetch portalConfig: ${response.status} ${response.statusText}`,
    )
  }

  return await response.json()
}
/**
 * END helper functions you could also move to a helper file
 */

onMounted(async () => {
  try {
    portalConfig.value = await getPortalConfig(authToken)
    layerConfig.value = await getLayerConfig(authToken)
  } catch (error) {
    console.error('Error loading configs:', error.message)
  }
})

// ///end readme

const drawing = computed(() => {
  return initDrawing ?
    transformFeatureCollection(JSON.parse(initDrawing), 'EPSG:3857', 'EPSG:4326') :
    ''
})
const emit = defineEmits(['locationDrawing'])

const instance = getCurrentInstance()
const store = useStore()

instance.appContext.app.mixin(prefixClassMixin)

const extent = [10.3, 53.2347, 10.4, 53.25]

const customLayerList = [
  {
    id: '3333',
    name: 'HafenCity14 - Vektor',
    url: 'https://hh.xplanungsplattform.de/xplan-wms/services/wms',
    typ: 'WMS',
    layers: 'BP_Planvektor',
    format: 'image/png',
    version: '1.1.1',
    singleTile: false,
    transparent: true,
    transparency: 0,
    gutter: 0,
    minScale: '0',
    maxScale: '2500000',
    tilesize: 512,
    visibleOnLoad: true,
  },
  {
    id: '4444',
    name: 'HafenCity14 - Raster',
    url: 'https://hh.xplanungsplattform.de/xplan-wms/services/wms',
    typ: 'WMS',
    layers: 'BP_Planraster',
    format: 'image/png',
    version: '1.1.1',
    singleTile: false,
    transparent: true,
    transparency: 0,
    gutter: 0,
    minScale: '0',
    maxScale: '2500000',
    tilesize: 512,
    visibleOnLoad: true,
  },
]

const customLayerConfigurationList = [
  {
    Titel: 'Vektor Layer',
    Layer: [
      {
        id: '3333',
      },
    ],
  },
  {
    Titel: 'Raster Layer',
    Layer: [
      {
        id: '4444',
      },
    ],
  },
]

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
  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })
})

</script>
