<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

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
import { checkResponse, dpRpc } from '@demos-europe/demosplan-ui'

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
    loadComponents () {
      dpRpc('addons.assets.load', { hookName: this.hookName })
        .then(response => checkResponse(response))
        .then(response => {
          const result = response[0].result

          for (const key of Object.keys(result)) {
            const addon = result[key]
            if (addon === undefined) {
              /*
               * If for some reason we don't receive a valid info object from the backend
               * we'll just skip it.
               */
              console.debug('Skipping addon hook response evaluation for ' + key)
              continue
            }

            const contentKey = addon.entry + '.umd.js'
            const content = addon.content[contentKey]

            /*
             * While eval generally is a BADIDEA, we really need to evaluate the code we're
             * adding dynamically to use the provided addon's script henceforth.
             */
            eval(content)
            this.$options.components[addon.entry] = window[addon.entry].default

            this.component = window[addon.entry].default
          }
        })
    }
  },

  mounted () {
    this.loadComponents()
  }
}
</script>
