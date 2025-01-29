/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { apiData } from '../__mocks__/layer_json.mock'
import { createLocalVue } from '@vue/test-utils'
import Layers from '@DpJs/store/map/Layers'
import Vuex from 'vuex'

const LocalVue = createLocalVue()
LocalVue.use(Vuex)
let StubStore

// Non-instance tests
describe('Layer-Store', () => {
  it('is namespaced', () => {
    expect(Layers.hasOwnProperty('namespaced')).toBe(true)
    expect(Layers.namespaced).toBe(true)
  })

  it('has a bunch of states', () => {
    expect(Layers.hasOwnProperty('state')).toBe(true)
    expect(Layers.state.hasOwnProperty('apiData')).toBe(true)
    expect(typeof Layers.state.apiData).toBe('object')
    expect(Layers.state.hasOwnProperty('originalApiData')).toBe(true)
    expect(typeof Layers.state.originalApiData).toBe('object')
    expect(Layers.state.hasOwnProperty('legends')).toBe(true)
    expect(Layers.state.legends instanceof Array).toBe(true)
    expect(Layers.state.hasOwnProperty('procedureId')).toBe(true)
    expect(typeof Layers.state.procedureId).toBe('string')
    expect(Layers.state.hasOwnProperty('draggableOptions')).toBe(true)
    expect(typeof Layers.state.draggableOptions).toBe('object')
  })
})

// Active tests
describe('Layers', () => {
  beforeEach(() => {
    StubStore = new Vuex.Store({})
    StubStore.registerModule('Layers', Layers)
  })

  it('can store data', () => {
    expect(StubStore.state.Layers.apiData).toEqual({})
    StubStore.commit('Layers/updateApiData', apiData)
    expect(typeof StubStore.state.Layers.apiData.data).toBe('object')
  })

  it('can store the original data to restore the loaded state which is a real clone of the data which gets manipulated', () => {
    StubStore.commit('Layers/updateApiData', apiData)
    StubStore.commit('Layers/saveOriginalState', JSON.parse(JSON.stringify(apiData)))
    expect(StubStore.state.Layers.apiData).toEqual(StubStore.state.Layers.originalApiData)

    StubStore.state.Layers.apiData.data.id = 'xxx-xxx-xxx'
    StubStore.state.Layers.apiData.included.splice(0, 1)
    expect(StubStore.state.Layers.apiData).not.toEqual(StubStore.state.Layers.originalApiData)
  })
})
