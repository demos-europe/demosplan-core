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

const transformInitialExtent = () => {
  transformedInitialExtent.value = transformExtent(initialExtent, 'EPSG:3857', 'EPSG:4326')
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
