<template>
  <div>
    <component
      :is="component"
      v-bind="getCurrentProperties()"
    />
  </div>
</template>

<script>
import { checkResponse, dpRpc } from '@demos-europe/demosplan-utils'
export default {
  name: 'AddonWrapper',

  props: {
    hookName: {
      type: String,
      required: true,
      default: ''
    },

    addonProps: {
      type: Object,
      required: false,
      default: () => {}
    }
  },

  data () {
    return {
      component: ''
    }
  },

  methods: {
    getCurrentProperties() {
      return this.addonProps
    },

    loadComponents(hookName) {
      const params = {
        hookName: hookName
      }

      dpRpc('addons.assets.load', params)
        .then(response => checkResponse(response))
        .then(response => {
          const result = response[0].result

          for (const key of Object.keys(result)) {
            const addon = result[key]
            const contentKey = addon.entry + '.umd.js'
            const content = addon.content[contentKey]

            eval(content)
            this.$options.components[addon.entry] = window[addon.entry].default

            this.component = window[addon.entry].default
          }
        })
    },
  },

  mounted () {
    this.loadComponents(this.hookName)
  }
}
</script>
