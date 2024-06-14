<template>
  <component
    :is="component"
    :ref="refComponent"
    v-bind="addonProps"
    @addonEvent:emit="(event) => $emit(event.name, event.payload)" />
</template>

<script>
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'

export default {
  name: 'AddonWrapper',

  props: {
    /**
     * The addonProps prop will be bound to the addon component to add props dynamically.
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
      default: ''
    }
  },

  data () {
    return {
      component: '',
      loadedAddons: []
    }
  },

  methods: {
    loadComponents () {
      loadAddonComponents(this.hookName)
        .then(addons => {
          addons.forEach(addon => {
            this.$options.components[addon.name] = window[addon.name].default
            this.component = window[addon.name].default
            this.loadedAddons.push(addon.name)
          })

          this.$emit('addons:loaded', this.loadedAddons)
        })
    }
  },

  mounted () {
    this.loadComponents()
  }
}
</script>
