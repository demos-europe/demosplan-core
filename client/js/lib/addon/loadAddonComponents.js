import { checkResponse, dpRpc } from '@demos-europe/demosplan-ui'

export default function loadAddonComponents (hookName) {
  const params = {
    hookName: hookName
  }

  const addons = []

  return dpRpc('addons.assets.load', params)
    .then(response => checkResponse(response))
    .then(response => {
      const result = response[0].result

      for (const key of Object.keys(result)) {
        const addon = result[key]
        const contentKey = addon.entry + '.umd.js'
        const content = addon.content[contentKey]

        addons.push(content)
      }
    })
}
