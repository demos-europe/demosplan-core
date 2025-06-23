<template>
  <div class="h-full w-full">
    <dp-button
      id="statementModalButton"
      :class="prefixClass('left-[365px] top-[24px] pt-[11px] pb-[11px] pl-[20px] pr-[20px] !absolute z-above-zero')"
      data-cy="statementModal"
      rounded
      :text="Translator.trans('statement.participate')"
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
  }
})

const emit = defineEmits(['locationDrawing'])

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
  const payload = {
    "r_location": "point",
    "r_location_geometry": event.detail,
    "r_location_priority_area_key": "",
    "r_location_priority_area_type": "",
    "r_location_point": "",
    "location_is_set": "geometry"
  }
  emit('locationDrawing', payload)
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
