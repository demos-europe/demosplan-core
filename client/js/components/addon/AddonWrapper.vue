<template>
  <component
    :is="component"
    :ref="refComponent"
    v-bind="addonProps"
    @addonEvent:emit="(event) => $emit(event.name, event.payload)" />
</template>

<script>
import { checkResponse, dpRpc } from '@demos-europe/demosplan-ui'

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
      if (window.dplan.loadedAddons[this.hookName]) {
        return
      }

      window.dplan.loadedAddons[this.hookName] = true
      dpRpc('addons.assets.load', { hookName: this.hookName })
        .then(response => checkResponse(response))
        .then(response => {
          const result = response[0].result

          for (const key of Object.keys(result)) {
            const addon = result[key]
            if (addon === undefined) {
              /*
               * If for some reason we don't receive a valid response object from the backend
               * we'll just skip it.
               */
              console.debug('Skipping addon hook response evaluation for ' + key)
              continue
            }

            const contentKey = addon.entry + '.umd.js'
            const content = addon.content[contentKey]

            /*
             * While eval is generally a BAD IDEA, we really need to evaluate the code
             * we're adding dynamically to use the provided addon's script from now on.
             */
            // eslint-disable-next-line no-eval
            eval(content)
            this.$options.components[addon.entry] = window[addon.entry].default

            this.component = window[addon.entry].default

            this.loadedAddons.push(addon.entry)
          }

          this.$emit('addons:loaded', this.loadedAddons)
        })
    }
  },

  mounted () {
    this.loadComponents()
  }
}
</script>
