import { enableAutoUnmount, shallowMount } from '@vue/test-utils'
import DpMapSettingsPreview from '@DpJs/components/document/DpMapSettingsPreview.vue'
import { vi } from 'vitest'

vi.mock('@demos-europe/demosplan-ui', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  dpApi: { get: vi.fn(() => Promise.reject(new Error('not needed'))) },
}))

// The real map pulls in masterportal, which does not resolve under vitest
vi.mock('@DpJs/components/map/map/DpOlMap.vue', () => ({
  default: { name: 'DpOlMap', template: '<div><slot /></div>' },
}))

enableAutoUnmount(afterEach)

const mountPreview = (territory: string) => shallowMount(DpMapSettingsPreview, {
  props: { territory },
  global: {
    stubs: { DpOlMap: false },
  },
})

const territoryLayer = (wrapper: ReturnType<typeof mountPreview>) =>
  wrapper.findAllComponents({ name: 'DpOlMapLayerVector' })
    .filter(layer => layer.props('name') === 'mapSettingsPreviewTerritory')

describe('DpMapSettingsPreview territory layer', () => {
  it.each([
    ['empty string', ''],
    ['empty array', '[]'],
    ['empty object', '{}'],
  ])('does not render the layer for %s', (_label, territory) => {
    expect(territoryLayer(mountPreview(territory))).toHaveLength(0)
  })

  it('renders the layer for a GeoJSON feature collection', () => {
    const territory = JSON.stringify({ type: 'FeatureCollection', features: [] })

    expect(territoryLayer(mountPreview(territory))).toHaveLength(1)
  })
})
