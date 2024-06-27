<template>
  <div>
    <component
      v-for="addon in loadedAddons"
      :is="addon.component"
      :key="`addon:${addon.name}`"
      :data-cy="`addon:${addon.name}`"
      :ref="`${addon.name}${refComponent}`"
      v-bind="addonProps"
      @addonEvent:emit="(event) => $emit(event.name, event.payload)" />
  </div>
</template>

<script>
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'

export default {
  name: 'AddonWrapper',

  props: {
    /**
     * The addonProps prop will be bound to the addon components to add props dynamically.
     */
    addonProps: {
      type: Object,
      required: false,
      default: () => {}
    },

    /**
     * The hookName prop will be used to load an addon via the generic rpc route.
     */
    hookName: {
      type: String,
      required: true,
      default: ''
    },

    refComponent: {
      type: String,
      required: false,
      default: 'Addon'
    }
  },

  data () {
    return {
      loadedAddons: []
    }
  },

  mounted () {
    loadAddonComponents(this.hookName)
      .then(addons => {
        addons.forEach(addon => {
          this.$options.components[addon.name] = window[addon.name].default
          this.loadedAddons.push({
            component: window[addon.name].default,
            name: addon.name
          })
        })

        this.$emit('addons:loaded', this.loadedAddons.map(addon => addon.name))
      })
  }
}
</script>
