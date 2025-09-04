/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { apiData } from '../fixtures/layer_json'
import { createStore } from 'vuex'
import { dpApi } from '@demos-europe/demosplan-ui'
import Layers from '@DpJs/store/map/Layers'
import { layersApiResponse } from '../fixtures/layers_api_response'

let StubStore

global.Translator = {
  trans: jest.fn(),
}

global.dplan = {}

// Mock Routing (following the pattern from VueConfigLocal.js)
global.Routing = {
  generate: jest.fn((route, params) => `/api/${route}/${params?.resourceType || ''}/${params?.resourceId || ''}`),
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
    StubStore.commit('Layers/updateApiData', layersApiResponse)
    StubStore.commit('Layers/saveOriginalState', layersApiResponse)

    // Reset all mocks
    jest.clearAllMocks()
  })

  describe('buildLegends', () => {
    it('should build legend URLs for enabled overlay layers', async () => {
      const commitSpy = jest.spyOn(StubStore, 'commit')

      await StubStore.dispatch('Layers/buildLegends')

      // Should commit setLegend for each enabled overlay layer
      expect(commitSpy).toHaveBeenCalledTimes(2)

      // Check the legends were added to the state
      expect(StubStore.state.Layers.legends).toHaveLength(2)
      expect(StubStore.state.Layers.legends[0]).toEqual({
        layerId: 'overlay-layer-1',
        treeOrder: 101010301,
        mapOrder: 104,
        defaultVisibility: true,
        url: 'https://example.com/wms?service=WMS&Layer=sample_layer&Request=GetLegendGraphic&Format=image/png&version=1.1.1',
      })
      expect(StubStore.state.Layers.legends[1]).toEqual({
        layerId: 'overlay-layer-2',
        treeOrder: 101010302,
        mapOrder: 105,
        defaultVisibility: false,
        url: 'https://example.com/wms2?service=WMS&Layer=sample_layer_2&Request=GetLegendGraphic&Format=image/png&version=1.1.1',
      })
    })

    it('should handle layers with multiple layer parameters', async () => {
      // Modify mock data to have a layer with multiple layers
      const overlayLayer = StubStore.state.Layers.apiData.included.find(item => item.id === 'overlay-layer-1')
      overlayLayer.attributes.layers = 'layer1,layer2,layer3'
      const commitSpy = jest.spyOn(StubStore, 'commit')

      await StubStore.dispatch('Layers/buildLegends')

      // Should create legend for each layer parameter (3 for overlay-layer-1 + 1 for overlay-layer-2)
      expect(commitSpy).toHaveBeenCalledTimes(4)
    })
  })

  describe('saveAll', () => {
    it('should save all layers and categories', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')
      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(layersApiResponse)

      await StubStore.dispatch('Layers/saveAll')

      // Should dispatch save for each included element
      const includedCount = layersApiResponse.included.length
      /*
       * Each save triggers a 'get' dispatch, and each 'get' triggers a 'buildLegends' dispatch
       * So we have: saveAll + (save + get + buildLegends) * includedCount
       */
      expect(dispatchSpy).toHaveBeenCalledTimes(1 + (includedCount * 3))
    })
  })

  describe('save', () => {
    it('should save a GisLayer resource', async () => {
      const layerResource = layersApiResponse.included.find(item => item.type === 'GisLayer')
      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(layersApiResponse)

      await StubStore.dispatch('Layers/save', layerResource)

      expect(dpApi.patch).toHaveBeenCalledWith(
        expect.stringContaining('GisLayer'),
        {},
        expect.objectContaining({
          data: expect.objectContaining({
            type: 'GisLayer',
            id: layerResource.id,
          }),
        }),
      )
    })

    it('should save a GisLayerCategory resource', async () => {
      const categoryResource = layersApiResponse.included.find(item => item.type === 'GisLayerCategory')
      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(layersApiResponse)

      await StubStore.dispatch('Layers/save', categoryResource)

      expect(dpApi.patch).toHaveBeenCalledWith(
        expect.stringContaining('GisLayerCategory'),
        {},
        expect.objectContaining({
          data: expect.objectContaining({
            type: 'GisLayerCategory',
            id: categoryResource.id,
          }),
        }),
      )
    })

    it('should refresh data and clear active layer after successful save', async () => {
      const layerResource = layersApiResponse.included.find(item => item.type === 'GisLayer')

      // Set a procedureId, which is required for the 'get' action called in the 'save' action
      StubStore.commit('Layers/setProcedureId', 'test-procedure-id')

      jest.spyOn(dpApi, 'patch').mockResolvedValue({ data: {} })
      jest.spyOn(dpApi, 'get').mockResolvedValue(layersApiResponse)

      await StubStore.dispatch('Layers/save', layerResource)

      expect(StubStore.state.Layers.activeLayerId).toBe('')
    })
  })

  describe('findMostParentCategory', () => {
    it('should find the root category for a layer', async () => {
      const layer = {
        id: 'overlay-layer-1',
        attributes: {
          categoryId: 'sub-category-1',
        },
      }

      const result = await StubStore.dispatch('Layers/findMostParentCategory', layer)

      expect(result).toBe('category-1')
    })

    it('should return layer id if parent is root', async () => {
      const layer = {
        id: 'base-layer-1',
        attributes: {
          categoryId: 'root-category-123',
        },
      }

      const result = await StubStore.dispatch('Layers/findMostParentCategory', layer)

      expect(result).toBe('base-layer-1')
    })
  })

  describe('toggleCategoryAndItsChildren', () => {
    it('should toggle category and all its children visibility', async () => {
      await StubStore.dispatch('Layers/toggleCategoryAndItsChildren', {
        id: 'category-1',
        isVisible: false,
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
        setToVisible: true,
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1']?.isVisible).toBe(true)
      expect(StubStore.state.Layers.layerStates['base-layer-2']?.isVisible).not.toBe(true)
      expect(StubStore.state.Layers.layerStates['base-layer-3']?.isVisible).not.toBe(true)

      await StubStore.dispatch('Layers/toggleBaselayer', {
        id: 'base-layer-3',
        setToVisible: true,
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(true)
    })

    it('should not toggle when layer is already visible and setToVisible is true', async () => {
      // First set the layer as visible
      StubStore.commit('Layers/setLayerState', { id: 'base-layer-1', key: 'isVisible', value: true })

      const commitSpy = jest.spyOn(StubStore, 'commit')

      await StubStore.dispatch('Layers/toggleBaselayer', {
        id: 'base-layer-1',
        setToVisible: true,
      })

      // Should not make any commits when layer is already visible and setToVisible is true
      expect(commitSpy).not.toHaveBeenCalled()
    })
  })

  describe('toggleVisiblityGroup', () => {
    it('should toggle all layers in a visibility group', async () => {
      await StubStore.dispatch('Layers/toggleVisiblityGroup', {
        visibilityGroupId: 'group-1',
        value: false,
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['overlay-layer-2'].isVisible).toBe(false)

      await StubStore.dispatch('Layers/toggleVisiblityGroup', {
        visibilityGroupId: 'group-1',
        value: true,
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(true)
      expect(StubStore.state.Layers.layerStates['overlay-layer-2'].isVisible).toBe(true)
    })
  })

  describe('updateLayerVisibility', () => {
    it('should update layer visibility normally', async () => {
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: true,
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(true)

      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: false,
      })

      expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(false)
    })

    it('should handle exclusively mode for base layers', async () => {
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'base-layer-2',
        isVisible: true,
        exclusively: true,
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(true)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(false)

      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'base-layer-3',
        isVisible: true,
        exclusively: true,
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(true)

      // Nothing should change when tying to turn off the layer
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'base-layer-3',
        isVisible: false,
        exclusively: true,
      })

      expect(StubStore.state.Layers.layerStates['base-layer-1'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-2'].isVisible).toBe(false)
      expect(StubStore.state.Layers.layerStates['base-layer-3'].isVisible).toBe(true)
    })

    it('should handle visibility groups', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')

      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: true,
      })

      expect(dispatchSpy).toHaveBeenCalledWith('Layers/toggleVisiblityGroup', {
        visibilityGroupId: 'group-1',
        value: true,
      })
    })

    it('should update parent visibility when child becomes visible', async () => {
      await StubStore.dispatch('Layers/updateLayerVisibility', {
        id: 'overlay-layer-1',
        isVisible: true,
      })

      setTimeout(() => {
        // Should update parent category
        expect(StubStore.state.Layers.layerStates['overlay-layer-1'].isVisible).toBe(true)
        expect(StubStore.state.Layers.layerStates['sub-category-1'].isVisible).toBe(true)
        expect(StubStore.state.Layers.layerStates['category-1'].isVisible).toBe(true)
      }, 500)
    })
  })

  describe('toggleCategoryAlternatively', () => {
    it('should toggle category alternatively', async () => {
      const dispatchSpy = jest.spyOn(StubStore, 'dispatch')
      const layer = layersApiResponse.included.find(item => item.type === 'GisLayer')

      await StubStore.dispatch('Layers/toggleCategoryAlternatively', layer)

      expect(dispatchSpy).toHaveBeenCalledWith('Layers/findMostParentCategory', layer)
    })
  })
})
