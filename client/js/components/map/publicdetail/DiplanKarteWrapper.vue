<template>
  <div class="h-full w-full">
    <dp-button
      id="statementModalButton"
      :class="prefixClass('left-[365px] top-[24px] pt-[11px] pb-[11px] pl-[20px] pr-[20px] !absolute z-above-zero')"
      data-cy="statementModal"
      rounded
      :text="activeStatement ? Translator.trans('statement.participate.resume') : Translator.trans('statement.participate')"
      @click="openStatementModalOrLoginPage" />

    <diplan-karte
      @diplan-karte:geojson-update="handleDrawing"
    />
  </div>
</template>


<script setup>
import { DpButton, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { MapPlugin, registerWebComponent } from '@init/diplan-karten'
import { getCurrentInstance } from 'vue'

const props = defineProps({
  loginPath: {
    type: String,
    required: true
  },
  styleNonce: {
    type: String,
    required: true,
  },
  activeStatement: {
    type: Boolean,
    required: true
  }
})

const emit = defineEmits(['location-drawing'])

const instance = getCurrentInstance()

instance.appContext.app.mixin(prefixClassMixin)
instance.appContext.app.use(MapPlugin, {
  template: {
    compilerOptions: {
      isCustomElement: (tag) => tag === "diplan-karte",
    }
  }
})

const handleDrawing = (event) => {
  let payload = {}
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

const openStatementModalOrLoginPage= (event) => {
  if (!hasPermission('feature_new_statement')) {
    window.location.href = props.loginPath

    return
  }

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
</script>
