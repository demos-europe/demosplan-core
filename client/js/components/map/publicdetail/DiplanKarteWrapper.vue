<template>
  <div class="h-full w-full">
    <dp-button
      id="statementModalButton"
      :class="prefixClass('left-[365px] top-[24px] pt-[11px] pb-[11px] pl-[20px] pr-[20px] !absolute z-above-zero')"
      :text="activeStatement ? Translator.trans('statement.participate.resume') : Translator.trans('statement.participate')"
      data-cy="statementModal"
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
      profile="beteiligung"
      @diplan-karte:geojson-update="handleDrawing"
    />
  </div>
</template>


<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'
import { DpButton, prefixClassMixin, DpNotification } from '@demos-europe/demosplan-ui'
import { MapPlugin, registerWebComponent } from '@init/diplan-karten'
import { useStore } from 'vuex'

const props = defineProps({
  activeStatement: {
    type: Boolean,
    required: true
  },

  loginPath: {
    type: String,
    required: true
  },

  styleNonce: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['location-drawing'])

const store = useStore()

const instance = getCurrentInstance()

instance.appContext.app.mixin(prefixClassMixin)
instance.appContext.app.use(MapPlugin, {
  template: {
    compilerOptions: {
      isCustomElement: (tag) => tag === 'diplan-karte',
    }
  }
})

let isLocationInfoClosed = ref(false)

const isLocationToolSelected = computed(() => {
  return store.state.PublicStatement.activeActionBoxTab === 'draw'
})

const closeLocationInfo = () => {
  isLocationInfoClosed.value = true
}

const handleDrawing = (event) => {
  let payload

  // if all geometry was deleted, reset location reference
  if (event.detail[0].features.length === 0) {
    payload = {
      "r_location": "notLocated",
      "r_location_geometry": "",
      "r_location_point": "",
      "location_is_set": ""
    }
  } else {
    payload = {
      "r_location": "point",
      "r_location_geometry": JSON.stringify(event.detail[0]),
      "r_location_priority_area_key": "",
      "r_location_priority_area_type": "",
      "r_location_point": "",
      "location_is_set": "geometry"
    }
  }

  emit('location-drawing', payload)
}

const openStatementModalOrLoginPage = (event) => {
  if (!hasPermission('feature_new_statement')) {
    window.location.href = props.loginPath

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

registerWebComponent({
  nonce: props.styleNonce,
})

onMounted(() => {
  store.commit('PublicStatement/update', { key: 'activeActionBoxTab', val: 'talk' })
})

</script>
