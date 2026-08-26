<template>
  <div
    v-if="loadedAddons.length > 0"
    :class="wrapperClasses"
  >
    <component
      v-bind="{ demosplanUi, ...addonProps }"
      :is="addon.component"
      v-for="addon in loadedAddons"
      :key="`addon:${addon.name}`"
      :ref="`${addon.name}${refComponent}`"
      :data-cy="`addon:${addon.name}`"
      @addon-event:emit="(event) => $emit(event.name, event.payload)"
    />
  </div>
</template>

<script lang="ts">
import * as demosplanUi from '@demos-europe/demosplan-ui'
import { defineComponent, shallowRef, type ShallowRef } from 'vue'
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'

interface WrappedAddon {
  component: ShallowRef
  name: string
}

export default defineComponent({
  name: 'AddonWrapper',

  props: {
    /**
     * The addonProps prop will be bound to the addon components to add props dynamically.
     */
    addonProps: {
      type: Object,
      required: false,
      default: () => ({}),
    },

    /**
     * The hookName prop will be used to load an addon via the generic rpc route.
     */
    hookName: {
      type: String,
      required: true,
    },

    refComponent: {
      type: String,
      required: false,
      default: 'Addon',
    },

    wrapperClasses: {
      type: String,
      required: false,
      default: '',
    },
  },

  emits: [
    'addons:loaded',
  ],

  data () {
    return {
      demosplanUi: shallowRef(demosplanUi),
      loadedAddons: [] as WrappedAddon[],
    }
  },

  mounted () {
    loadAddonComponents(this.hookName)
      .then(addons => {
        addons.forEach(addon => {
          this.loadedAddons.push({
            component: addon.component,
            name: addon.name,
          })
        })

        this.$emit('addons:loaded', this.loadedAddons.map(addon => addon.name))
      })
  },
})
</script>
