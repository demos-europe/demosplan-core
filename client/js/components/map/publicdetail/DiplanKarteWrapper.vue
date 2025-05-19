<template>
  <div class="h-full w-full">
    <dp-button
      id="statementModalButton"
      :class="prefixClass('left-[365px] top-[24px] absolute z-above-zero')"
      data-cy="statementModal"
      :disabled="!hasPermission('feature_new_statement')"
      href="#publicStatementForm"
      rounded
      :text="Translator.trans('statement.participate')"
      @click.stop.prevent="() => hasPermission('feature_new_statement') ? toggleStatementModal({}) : null" />

    <diplan-karte />
  </div>
</template>


<script setup>
import { DpButton, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { MapPlugin } from '@init/diplan-karten'
import { getCurrentInstance } from 'vue'

const instance = getCurrentInstance()

instance.appContext.app.mixin(prefixClassMixin)
instance.appContext.app.use(MapPlugin, {
  template: {
    compilerOptions: {
      // Register the custom element:
      isCustomElement: (tag) => tag === "diplan-karte",
    }
  }
})

const toggleStatementModal = (updateStatementPayload) => {
  instance.parent.components.StatementModal.methods.toggleModal(true, updateStatementPayload)
}
</script>
