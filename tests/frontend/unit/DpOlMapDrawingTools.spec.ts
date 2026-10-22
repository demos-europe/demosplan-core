import { defineComponent, h } from 'vue'
import { Draw, Select } from 'ol/interaction'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import DpOlMapDrawFeature from '@DpJs/components/map/map/DpOlMapDrawFeature.vue'
import DpOlMapEditFeature from '@DpJs/components/map/map/DpOlMapEditFeature.vue'
import { vi } from 'vitest'

enableAutoUnmount(afterEach)

const createMap = () => {
  const layers: unknown[] = []
  const interactions: unknown[] = []

  return {
    interactions,
    addInteraction: vi.fn(i => interactions.push(i)),
    removeInteraction: vi.fn(i => interactions.splice(interactions.indexOf(i), 1)),
    addLayer: vi.fn(l => layers.push(l)),
    getLayers: () => ({ forEach: (cb: (l: unknown) => void) => layers.forEach(cb) }),
    getView: () => ({ getProjection: () => 'EPSG:3857' }),
    render: vi.fn(),
    updateSize: vi.fn(),
  }
}

// Stands in for DpOlMap, which provides olMapState to its slot content
const mountMapWithTools = (map: ReturnType<typeof createMap>) => mount(defineComponent({
  provide () {
    return { olMapState: this.olMapState }
  },

  data () {
    return { olMapState: { activeTool: '', drawStyles: {}, map } }
  },

  render () {
    return h('div', [
      h(DpOlMapDrawFeature, { name: 'Polygon', renderControl: true, type: 'Polygon' }),
      h(DpOlMapEditFeature, { name: 'Edit', target: 'Polygon' }),
    ])
  },
}), {
  // DpOlMapEditFeature walks up to <html> to read z-indexes
  attachTo: document.body,
})

const hasInteraction = (map: ReturnType<typeof createMap>, type: typeof Draw | typeof Select) =>
  map.interactions.some(i => i instanceof type)

describe('DpOlMapDrawFeature / DpOlMapEditFeature', () => {
  it('activates a tool on click and deactivates it on second click', async () => {
    const map = createMap()
    const wrapper = mountMapWithTools(map)
    const drawButton = wrapper.findComponent(DpOlMapDrawFeature).find('button')

    await drawButton.trigger('click')
    expect(hasInteraction(map, Draw)).toBe(true)

    await drawButton.trigger('click')
    expect(hasInteraction(map, Draw)).toBe(false)
  })

  it('deactivates the other tool when switching', async () => {
    const map = createMap()
    const wrapper = mountMapWithTools(map)

    await wrapper.findComponent(DpOlMapDrawFeature).find('button').trigger('click')
    await wrapper.findComponent(DpOlMapEditFeature).find('[data-cy="editButtonDesc"]').trigger('click')

    expect(hasInteraction(map, Draw)).toBe(false)
    expect(hasInteraction(map, Select)).toBe(true)
  })

  it('does not affect tools of another map', async () => {
    const map = createMap()
    const otherMap = createMap()
    const wrapper = mountMapWithTools(map)

    mountMapWithTools(otherMap)

    await wrapper.findComponent(DpOlMapDrawFeature).find('button').trigger('click')

    expect(otherMap.addInteraction).not.toHaveBeenCalled()
  })
})
