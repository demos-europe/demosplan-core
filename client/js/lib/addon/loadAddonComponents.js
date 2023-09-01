import { checkResponse, dpRpc } from '@demos-europe/demosplan-ui'
import Vue from 'vue'

export default async function loadAddonComponents (hookName) {
  const params = {
    hookName: hookName
  }

  return dpRpc('addons.assets.load', params)
    .then(response => checkResponse(response))
    .then(response => {
      const result = response[0].result
      const addons = []

      for (const key of Object.keys(result)) {
        const addon = result[key]
        const contentKey = addon.entry + '.umd.js'
        const content = addon.content[contentKey]

        addons.push({
          entry: content,
          name: addon.entry,
          options: addon.options ?? ''
        })

        eval(content)
      }

      return addons
    })
}

