import { dpRpc } from '@demos-europe/demosplan-ui'

/**
 * Legacy path for addons still built as UMD bundles. While eval is generally a BAD IDEA,
 * older addon builds ship no ES module to import(), only a global to eval into existence.
 * Remove once every addon has migrated to the ESM builder output!
 *
 * @param {object} addon
 */
function legacyLoadAddon (addon) {
  const contentKey = addon.entry + '.umd.js'

  eval(addon.content[contentKey])

  return window[addon.entry].default
}

/**
 * Handle loading the frontend assets for an addon
 *
 * @param {object} addon
 * @returns {Promise<void>}
 */
async function loadAddon (addon) {
  if (addon === undefined) {
    /*
     * If for some reason we don't receive a valid response object from the backend
     * we'll just skip it.
     */
    throw Error('Addon is undefined')
  }

  let component

  if (addon.urls) {
    const urlKey = `${addon.entry}.esm.js`
    const module = await import(/* webpackIgnore: true */ addon.urls[urlKey])

    component = module.default
  } else {
    component = legacyLoadAddon(addon)
  }

  return {
    component,
    name: addon.entry,
    options: addon.options ?? '',
  }
}

/**
 * Process addon registration and loading for a given hook
 *
 * @param {string} hookName
 * @returns {Promise<*|*[]>}
 */
async function loadAddonComponents (hookName) {
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

        try {
          let loadedAddon = await loadAddon(addon, key, addons)

          addons.push(loadedAddon)
        } catch (error) {
          console.debug(`An error occurred while loading addon ${key}`, error)
        }
      }

      window.dplan.loadedAddons[hookName] = addons

      return addons
    })
}

export default loadAddonComponents
