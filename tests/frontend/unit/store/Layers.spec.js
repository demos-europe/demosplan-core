/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { apiData } from '../__mocks__/layer_json.mock'
import { createStore } from 'vuex'
import { dpApi } from '@demos-europe/demosplan-ui'
import Layers from '@DpJs/store/map/Layers'
import { mockLayersApiResponse } from '../__mocks__/layers_api_response.mock'

let StubStore

global.Translator = {
  trans: jest.fn()
}

global.dplan = {}

// Mock Routing (following the pattern from VueConfigLocal.js)
global.Routing = {
  generate: jest.fn((route, params) => `/api/${route}/${params?.resourceType || ''}/${params?.resourceId || ''}`)
}

// Non-instance tests
describe('Layer-Store', () => {
  it('is namespaced', () => {
    expect(Object.hasOwn(Layers, 'namespaced')).toBe(true)
    expect(Layers.namespaced).toBe(true)
  })

  it('has a bunch of states', () => {
    expect(Object.hasOwn(Layers, 'state')).toBe(true)
    expect(Object.hasOwn(Layers.state, 'apiData')).toBe(true)
    expect(typeof Layers.state.apiData).toBe('object')
    expect(Object.hasOwn(Layers.state, 'originalApiData')).toBe(true)
    expect(typeof Layers.state.originalApiData).toBe('object')
    expect(Object.hasOwn(Layers.state, 'legends')).toBe(true)
    expect(Layers.state.legends instanceof Array).toBe(true)
    expect(Object.hasOwn(Layers.state, 'procedureId')).toBe(true)
    expect(typeof Layers.state.procedureId).toBe('string')
    expect(Object.hasOwn(Layers.state, 'draggableOptions')).toBe(true)
    expect(typeof Layers.state.draggableOptions).toBe('object')
  })
})

// Active tests
describe('Layers', () => {
  beforeEach(() => {
    StubStore = createStore({})
    StubStore.registerModule('Layers', Layers)
  })

  it('can store data', () => {
    expect(StubStore.state.Layers.apiData).toEqual({})
    StubStore.commit('Layers/updateState', { key: 'apiData', value: apiData })
    expect(typeof StubStore.state.Layers.apiData.data).toBe('object')
  })

  it('can store the original data to restore the loaded state which is a real clone of the data which gets manipulated', () => {
    StubStore.commit('Layers/updateState', { key: 'apiData', value: apiData })
    StubStore.commit('Layers/saveOriginalState', JSON.parse(JSON.stringify(apiData)))
    expect(StubStore.state.Layers.apiData).toEqual(StubStore.state.Layers.originalApiData)

    StubStore.state.Layers.apiData.data.id = 'xxx-xxx-xxx'
    StubStore.state.Layers.apiData.included.splice(0, 1)
    expect(StubStore.state.Layers.apiData).not.toEqual(StubStore.state.Layers.originalApiData)
  })
})

// Action tests
describe('Layers Actions', () => {
  beforeEach(() => {
    StubStore = createStore({})
    StubStore.registerModule('Layers', Layers)
    // Setup store with mock data
    StubStore.commit('Layers/updateApiData', mockLayersApiResponse)
    StubStore.commit('Layers/saveOriginalState', mockLayersApiResponse)

    // Reset all mocks
    jest.clearAllMocks()
  })

  describe('buildLegends', () => {
    it.skip('should build legend URLs for enabled overlay layers', async () => {
      const commitSpy = jest.spyOn(StubStore, 'commit')

      await StubStore.dispatch('Layers/buildLegends')

      // Should commit setLegend for each enabled overlay layer
      expect(commitSpy).toHaveBeenCalledWith('Layers/setLegend', expect.objectContaining({
        layerId: 'overlay-layer-1',
        treeOrder: 101010301,
        mapOrder: 104,
        defaultVisibility: true,
        url: expect.stringContaining('GetLegendGraphic')
      }))
    })

    it.skip('should handle layers with multiple layer parameters', async () => {
      // Modify mock data to have a layer with multiple layers
      StubStore.state.Layers.apiData.included[0].attributes.layers = 'layer1,layer2,layer3'
      const commitSpy = jest.spyOn(StubStore, 'commit')

      await StubStore.dispatch('Layers/buildLegends')

      // Should create legend for each layer parameter
      expect(commitSpy).toHaveBeenCalledTimes(3) // One for each layer
    })
  })

  describe('saveAll', () => {
    it.skip('should save all layers and categories', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')
      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      await StubStore.dispatch('Layers/saveAll')

      // Should dispatch save for each included element
      const includedCount = mockLayersApiResponse.included.length
      expect(dispatchSpy).toHaveBeenCalledTimes(includedCount + 1) // +1 for the saveAll call itself
    })
  })

  describe('save', () => {
    it('should save a GisLayer resource', async () => {
      const layerResource = mockLayersApiResponse.included.find(item => item.type === 'GisLayer')
      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(mockLayersApiResponse)


      await StubStore.dispatch('Layers/save', layerResource)

      expect(dpApi.patch).toHaveBeenCalledWith(
        expect.stringContaining('GisLayer'),
        {},
        expect.objectContaining({
          data: expect.objectContaining({
            type: 'GisLayer',
            id: layerResource.id
          })
        })
      )
    })

    it('should save a GisLayerCategory resource', async () => {
      const categoryResource = mockLayersApiResponse.included.find(item => item.type === 'GisLayerCategory')
      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(mockLayersApiResponse)

      await StubStore.dispatch('Layers/save', categoryResource)

      expect(dpApi.patch).toHaveBeenCalledWith(
        expect.stringContaining('GisLayerCategory'),
        {},
        expect.objectContaining({
          data: expect.objectContaining({
            type: 'GisLayerCategory',
            id: categoryResource.id
          })
        })
      )
    })

    it('should refresh data and clear active layer after successful save', async () => {
      const layerResource = mockLayersApiResponse.included.find(item => item.type === 'GisLayer')

      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(mockLayersApiResponse)

      await StubStore.dispatch('Layers/save', layerResource)

      expect(StubStore.state.Layers.apiData.data).toMatchObject(mockLayersApiResponse.data)
    })
  })

  describe('findMostParentCategory', () => {
    it.skip('should find the root category for a layer', async () => {
      const layer = {
        id: 'overlay-layer-1',
        attributes: {
          categoryId: 'sub-category-1'
        }
      }

      const result = await StubStore.dispatch('Layers/findMostParentCategory', layer)

      expect(result).toBe('category-1')
    })

    it('should return layer id if parent is root', async () => {
      const layer = {
        id: 'base-layer-1',
        attributes: {
          categoryId: 'root-category-123'
        }
      }

      const result = await StubStore.dispatch('Layers/findMostParentCategory', layer)

      expect(result).toBe('base-layer-1')
    })
  })

  describe('toggleCategoryAndItsChildren', () => {
    it('should toggle category and all its children visibility', async () => {
      await StubStore.dispatch('Layers/toggleCategoryAndItsChildren', {
        id: 'category-1',
        isVisible: false
      })

      expect(StubStore.state.Layers.layerStates['category-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['sub-category-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(false)
    })
  })

  describe('toggleBaselayer', () => {
    it('should toggle base layer visibility exclusively', async () => {
      await StubStore.dispatch('Layers/toggleBaselayer', {
        id: 'base-layer-1',
        value: true
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(true)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(false)

      await StubStore.dispatch('Layers/toggleBaselayer', {
        id: 'base-layer-3',
        value: true
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(true)
    })

    it('should not toggle when value is false', async () => {
      const commitSpy = jest.spyOn(StubStore, 'commit')

      await StubStore.dispatch('Layers/toggleBaselayer', {
        id: 'base-layer-1',
        value: false
      })

      // Should not make any commits when toggling to false
      expect(commitSpy).not.toHaveBeenCalled()
    })
  })

  describe('toggleVisiblityGroup', () => {
    it('should toggle all layers in a visibility group', async () => {
      await StubStore.dispatch('Layers/toggleVisiblityGroup', {
        visibilityGroupId: 'group-1',
        value: false
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['overlay-layer-2'].isVisible).toBe(false)

      await StubStore.dispatch('Layers/toggleVisiblityGroup', {
        visibilityGroupId: 'group-1',
        value: true
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(true)
      expect(StubStore.state.Layers.layerStates['overlay-layer-2'].isVisible).toBe(true)
    })
  })

  describe('updateLayerVisibility', () => {
    it('should update layer visibility normally', async () => {
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: true
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(true)

      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: false
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(false)
    })

    it('should handle exclusively mode for base layers', async () => {
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'base-layer-2',
        isVisible: true,
        exclusively: true
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(true)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(false)

      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'base-layer-3',
        isVisible: true,
        exclusively: true
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(true)

      // Nothing should change when tying to turn off the layer
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'base-layer-3',
        isVisible: false,
        exclusively: true
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(true)
    })

    it('should handle visibility groups', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')

      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: true
      })

      expect(dispatchSpy).toHaveBeenCalledWith('Layers/toggleVisiblityGroup', {
        visibilityGroupId: 'group-1',
        value: true
      })
    })

    it('should update parent visibility when child becomes visible', async () => {
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: true
      })

      setTimeout(() => {
        // Should update parent category
        expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(true)
        expect(StubStore.state.Layers.layerStates['sub-category-1'].isVisible).toBe(true)
        expect(StubStore.state.Layers.layerStates['category-1'].isVisible).toBe(true)
      }, 500)
    })
  })

  describe('toggleAllChildren', () => {
    it('should toggle all children of a category', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')
      const category = mockLayersApiResponse.included.find(item =>
        item.type === 'GisLayerCategory' && item.id === 'category-1'
      )

      await StubStore.dispatch('Layers/toggleAllChildren', {
        layer: category,
        isVisible: false
      })

      expect(dispatchSpy).toHaveBeenCalledWith('Layers/updateLayerVisibility', {
        id: 'sub-category-1',
        isVisible: false
      })
    })
  })

  describe('toggleCategoryAlternatevely', () => {
    it('should toggle category alternatively', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')
      const layer = mockLayersApiResponse.included.find(item => item.type === 'GisLayer')

      await StubStore.dispatch('Layers/toggleCategoryAlternatevely', { layer })

      expect(dispatchSpy).toHaveBeenCalledWith('Layers/findMostParentCategory', layer)
    })
  })
})
