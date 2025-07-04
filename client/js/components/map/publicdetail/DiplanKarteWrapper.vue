<template>
  <div class="h-full w-full">
    <dp-button
      id="statementModalButton"
      :class="prefixClass('left-[365px] top-[24px] pt-[11px] pb-[11px] pl-[20px] pr-[20px] !absolute z-above-zero')"
      :text="activeStatement ? Translator.trans('statement.participate.resume') : Translator.trans('statement.participate')"
      data-cy="publicStatementButton"
      rounded
      @click="openStatementModalOrLoginPage" />

    <diplan-karte
      v-if="isStoreAvailable"
      :geojson="drawing"
      profile="beteiligung"
      @diplan-karte:geojson-update="handleDrawing"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted } from 'vue'
import { DpButton, prefixClassMixin } from '@demos-europe/demosplan-ui'
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
      isCustomElement: (tag) => tag === 'diplan-karte'
    }
  }
})

const isStoreAvailable = computed(() => {
  return store.state.PublicStatement.storeInitialised
})

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
</script>
