import { dpRpc } from '@demos-europe/demosplan-ui'

export default async function loadAddonComponents (hookName) {
  while (window.dplan.loadedAddons[hookName] === 'pending') {
    await new Promise(resolve => setTimeout(resolve, 250))
  }

  if (window.dplan.loadedAddons[hookName] && typeof window.dplan.loadedAddons[hookName] === 'object') {
    return window.dplan.loadedAddons[hookName]
  }

  window.dplan.loadedAddons[hookName] = 'pending'

  const params = {
    hookName,
  }

  return await dpRpc('addons.assets.load', params)
    .then(async ({ data }) => {
      const result = data[0].result
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

        let component

        if (addon.urls) {
          const urlKey = addon.entry + '.esm.js'
          const module = await import(/* webpackIgnore: true */ addon.urls[urlKey])

          component = module.default
        } else {
          /*
           * Legacy path for addons still built as UMD bundles. While eval is generally a BAD IDEA,
           * older addon builds ship no ES module to import(), only a global to eval into existence.
           * Remove once every addon has migrated to the ESM builder output.
           */
          const contentKey = addon.entry + '.umd.js'

          eval(addon.content[contentKey])
          component = window[addon.entry].default
        }

        addons.push({
          component,
          name: addon.entry,
          options: addon.options ?? '',
        })
      }

      window.dplan.loadedAddons[hookName] = addons

      return addons
    })
}
