import { dpRpc } from '@demos-europe/demosplan-ui'
import { shallowRef, type ShallowRef } from 'vue'

type AddonHookOptions = Record<string, unknown>

interface EsmAddonEntry {
  entry: string
  options?: AddonHookOptions
  urls: Record<string, string>
}

interface LegacyAddonEntry {
  entry: string
  options?: AddonHookOptions
  content: Record<string, string>
}

type AddonRpcEntry = EsmAddonEntry | LegacyAddonEntry

interface AddonRpcResponse {
  data: Array<{ result: Record<string, AddonRpcEntry | undefined> }>
}

export interface LoadedAddon {
  component: ShallowRef
  name: string
  options: AddonHookOptions | string
}

/*
 * ESM addons ship a real module to import(). Legacy UMD addons ship no module - just a global
 * to eval into existence. While eval is generally a BAD IDEA, that's still needed here until
 * every addon has migrated to the ESM builder output.
 */
async function resolveComponent (addon: AddonRpcEntry): Promise<unknown> {
  if ('urls' in addon) {
    const urlKey = `${addon.entry}.esm.js`
    const module = await import(/* webpackIgnore: true */ addon.urls[urlKey])

    return module.default
  }

  const contentKey = `${addon.entry}.umd.js`

  console.warn(`Loading legacy UMD addon ${addon.entry} via eval. This is not recommended and will be removed in the future.`)

  eval(addon.content[contentKey])

  return (window as unknown as Record<string, { default: unknown }>)[addon.entry].default
}

export default async function loadAddonComponents (hookName: string): Promise<LoadedAddon[]> {
  while (dplan.loadedAddons[hookName] === 'pending') {
    await new Promise(resolve => setTimeout(resolve, 250))
  }

  const cached = dplan.loadedAddons[hookName]

  if (cached && typeof cached === 'object') {
    return cached as LoadedAddon[]
  }

  dplan.loadedAddons[hookName] = 'pending'

  const { data } = await dpRpc('addons.assets.load', { hookName }) as AddonRpcResponse
  const entries = data[0].result
  const addons: LoadedAddon[] = []

  for (const [key, addon] of Object.entries(entries)) {
    if (addon === undefined) {
      /*
       * If for some reason we don't receive a valid response object from the backend
       * we'll just skip it.
       */
      console.debug('Skipping addon hook response evaluation for ' + key)
      continue
    }

    addons.push({
      component: await shallowRef(resolveComponent(addon)),
      name: addon.entry,
      options: addon.options ?? '',
    })
  }

  dplan.loadedAddons[hookName] = addons

  return addons
}
