<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <component
      :is="component"
      v-bind="addonProps"
    />
  </div>
</template>

<script>
import { checkResponse, dpRpc } from '@demos-europe/demosplan-utils'
export default {
  name: 'AddonWrapper',

  props: {
    /**
     * The hookName prop will be used to load an addon via the generic rpc route.
     */
    hookName: {
      type: String,
      required: true,
      default: ''
    },

    /**
     * The addonProps prop will be binded to the addon component to add props dynamically.
     */
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
