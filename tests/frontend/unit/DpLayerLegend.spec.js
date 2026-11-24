/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import DpLayerLegend from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegend.vue'
import DpLayerLegendItem from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegendItem.vue'
import { layersApiResponse } from './fixtures/layers_api_response'
import LayersStore from '@DpJs/store/map/Layers'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const legendFile = id => ({
  id,
  name: `File of ${id}`,
  legend: { hash: `hash-${id}`, mimeType: 'application/pdf', fileSize: '1 KB' },
})

// Checks v-show without attaching the wrapper to the document
const isShown = domWrapper => domWrapper.element.style.display !== 'none'

// The module's state is a plain object; give each store its own copy
const createLayersStore = () => createStore({
  modules: {
    Layers: {
      ...LayersStore,
      state: JSON.parse(JSON.stringify(LayersStore.state)),
    },
  },
})

describe('DpLayerLegend', () => {
  let store
  let wrapper

  const mountLegend = (layersWithLegendFiles = []) => {
    wrapper = shallowMountWithGlobalMocks(DpLayerLegend, {
      props: {
        layersWithLegendFiles,
        planPdf: {},
        procedureId: '123',
      },
      global: {
        plugins: [store],
      },
    })
  }

  const section = layerType => wrapper.find(`[data-cy="layerLegend:${layerType}"]`)

  beforeEach(async () => {
    store = createLayersStore()
    store.commit('Layers/updateApiData', JSON.parse(JSON.stringify(layersApiResponse)))
    await store.dispatch('Layers/buildLegends')
  })

  it('should toggle unfolded state when toggle method is called', () => {
    mountLegend()

    expect(wrapper.vm.unfolded).toBe(false)
    wrapper.vm.toggle()
    expect(wrapper.vm.unfolded).toBe(true)
  })

  it('should list legend files of visible layers in the section of their layer type', () => {
    store.commit('Layers/setLayerState', { id: 'overlay-layer-1', key: 'isVisible', value: true })
    store.commit('Layers/setLayerState', { id: 'base-layer-1', key: 'isVisible', value: true })
    mountLegend([legendFile('overlay-layer-1'), legendFile('overlay-layer-2'), legendFile('base-layer-1')])

    expect(section('overlay').text()).toContain('File of overlay-layer-1')
    expect(section('overlay').text()).not.toContain('File of overlay-layer-2')
    expect(section('base').text()).toContain('File of base-layer-1')
  })

  it('should render legend images of visible layers only', () => {
    store.commit('Layers/setLayerState', { id: 'overlay-layer-2', key: 'isVisible', value: true })
    mountLegend()

    const legendItems = section('overlay').findAllComponents(DpLayerLegendItem)

    expect(legendItems).toHaveLength(1)
    expect(legendItems[0].props('legend').layerId).toBe('overlay-layer-2')
    expect(section('base').findAllComponents(DpLayerLegendItem)).toHaveLength(0)
  })

  it('should show a section heading only once one of its legend images has loaded', async () => {
    store.commit('Layers/setLayerState', { id: 'overlay-layer-1', key: 'isVisible', value: true })
    mountLegend()

    expect(isShown(section('overlay').find('h4'))).toBe(false)

    const [legend] = store.getters['Layers/visibleLegendsByLayerType'].overlay

    store.commit('Layers/markLegendAsLoaded', legend.url)
    await wrapper.vm.$nextTick()

    expect(isShown(section('overlay').find('h4'))).toBe(true)
    expect(isShown(section('base').find('h4'))).toBe(false)
  })

  it('should show a hint only while no legend of a visible layer is shown', async () => {
    mountLegend([legendFile('overlay-layer-2')])

    expect(wrapper.find('[data-cy="layerLegend:empty"]').exists()).toBe(true)

    store.commit('Layers/setLayerState', { id: 'overlay-layer-2', key: 'isVisible', value: true })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-cy="layerLegend:empty"]').exists()).toBe(false)
  })
})

describe('DpLayerLegendItem', () => {
  it('should stay hidden until its image has loaded', async () => {
    const store = createLayersStore()
    const legend = { layerId: 'overlay-layer-1', url: 'https://example.com/legend.png' }
    const wrapper = shallowMountWithGlobalMocks(DpLayerLegendItem, {
      props: { legend },
      global: { plugins: [store] },
    })

    expect(isShown(wrapper.find('li'))).toBe(false)

    await wrapper.find('img').trigger('load')

    expect(store.state.Layers.loadedLegends).toEqual([legend.url])
    expect(isShown(wrapper.find('li'))).toBe(true)
  })
})
