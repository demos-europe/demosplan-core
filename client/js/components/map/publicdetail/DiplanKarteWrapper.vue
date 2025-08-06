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
      profile="beteiligung"
      @diplan-karte:geojson-update="handleDrawing"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'
import { DpButton, DpNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { MapPlugin, registerWebComponent } from '@init/diplan-karten'
import { transformFeatureCollection } from '@DpJs/lib/map/transformFeature'
import { useStore } from 'vuex'

const { activeStatement, initDrawing, loginPath, styleNonce } = defineProps({
  activeStatement: {
    type: Boolean,
    required: true
  },

  initDrawing: {
    type: Object,
    required: false,
    default: () => ({
      type: 'FeatureCollection',
      features: []
    })
  },

  loginPath: {
    type: String,
    required: true
  },

  styleNonce: {
    type: String,
    required: true
  }
})

const drawing = computed(() => {
  return initDrawing
    ? transformFeatureCollection(JSON.parse(initDrawing), 'EPSG:3857', 'EPSG:4326')
    : ''
})
const emit = defineEmits(['locationDrawing'])

const instance = getCurrentInstance()
const store = useStore()

instance.appContext.app.mixin(prefixClassMixin)
instance.appContext.app.use(MapPlugin, {
  template: {
    compilerOptions: {
      isCustomElement: (tag) => tag === 'diplan-karte',
    }
  }
})

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
      location_is_set: ''
    }
  } else if (event.detail[0].features[0].properties?.type === 'PLACEMARK') {
    // We need to extract the coordinates to stay consistent with other location references
    const coordinates = geometry.features[0].geometry.coordinates
    const coordinateString = coordinates.join(',')
    payload = {
      r_location: 'point',
      r_location_point: coordinateString,
      r_location_priority_area_key: '',
      r_location_priority_area_type: '',
      r_location_geometry: '',
      location_is_set: 'point'
    }
  } else {
    payload = {
      r_location: 'point',
      r_location_geometry: JSON.stringify(geometry),
      r_location_priority_area_key: '',
      r_location_priority_area_type: '',
      r_location_point: '',
      location_is_set: 'geometry'
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
    nonce: styleNonce
  })
})

onMounted(() => {
  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })
})

</script>
