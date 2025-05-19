<template>
  <div
    aria-hidden="true"
    class="h-full w-full">
    <diplan-karte />
  </div>
</template>


<script setup>
import { MapPlugin } from '@init/diplan-karten'
import { getCurrentInstance } from 'vue'

const inst = getCurrentInstance()

console.log(inst)
inst.appContext.app.use(MapPlugin, {
  template: {
    compilerOptions: {
      // Register the custom element:
      isCustomElement: (tag) => tag === "diplan-karte",
    }
  }
})
</script>
