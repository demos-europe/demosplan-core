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
      :geojson="drawing"
      :fitToExtent.prop="transformedInitialExtent"
      enable-searchbar
      enable-toolbar
      profile="beteiligung"
      @diplan-karte:geojson-update="handleDrawing"
    />

    <!-- Copyright notice positioned at bottom right -->
    <div
      v-if="copyright"
      :class="prefixClass('left-[10px] bottom-[10px] !absolute z-above-zero bg-white bg-opacity-80 px-2 py-1 text-xs text-gray-600 rounded shadow-sm max-w-xs')"
      v-html="copyright"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'
import { DpButton, DpNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { registerWebComponent } from '@init/diplan-karten'
import { transformFeatureCollection } from '@DpJs/lib/map/transformFeature'
import { useStore } from 'vuex'

const { activeStatement, copyright, initDrawing, initialExtent, loginPath, maxExtent, styleNonce } = defineProps({
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

  maxExtent: {
    type: Array,
    required: false,
    default: () => [],
  },

  styleNonce: {
    type: String,
    required: true,
  },
})

const drawing = computed(() => {
  return initDrawing ?
    transformFeatureCollection(JSON.parse(initDrawing), 'EPSG:3857', 'EPSG:4326') :
    ''
})

// Transform initialExtent from EPSG:3857 to EPSG:4326 for diplan-karte
const transformedInitialExtent = computed(() => {
  if (!initialExtent || initialExtent.length !== 4) {
    return []
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

  // Transform the FeatureCollection
  const transformed = transformFeatureCollection(tempFeatureCollection, 'EPSG:3857', 'EPSG:4326')

  if (!transformed.features || transformed.features.length === 0) {
    return []
  }

  // Extract bounds from transformed coordinates
  const coords = transformed.features[0].geometry.coordinates[0]
  const lons = coords.map(coord => coord[0])
  const lats = coords.map(coord => coord[1])

  return [
    Math.min(...lons), // minX (longitude)
    Math.min(...lats), // minY (latitude)
    Math.max(...lons), // maxX (longitude)
    Math.max(...lats)  // maxY (latitude)
  ]
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

onMounted(() => {
  registerWebComponent({
    nonce: styleNonce,
  })
})

onMounted(() => {
  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })
})

</script>
