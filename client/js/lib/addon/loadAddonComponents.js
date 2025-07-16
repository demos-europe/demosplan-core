import { checkResponse, dpRpc } from '@demos-europe/demosplan-ui'

export default async function loadAddonComponents (hookName) {
  while (window.dplan.loadedAddons[hookName] === 'pending') {
    await new Promise(resolve => setTimeout(resolve, 250))
  }

  if (window.dplan.loadedAddons[hookName] && typeof window.dplan.loadedAddons[hookName] === 'object') {
    return window.dplan.loadedAddons[hookName]
  }

  window.dplan.loadedAddons[hookName] = 'pending'

  const params = {
    hookName
  }

  return await dpRpc('addons.assets.load', params)
    .then(response => checkResponse(response))
    .then(response => {
      const result = response[0].result
      const addons = []

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

        addons.push({
          entry: content,
          name: addon.entry,
          options: addon.options ?? ''
        })
      }

      window.dplan.loadedAddons[hookName] = addons

      return addons
    })
}
