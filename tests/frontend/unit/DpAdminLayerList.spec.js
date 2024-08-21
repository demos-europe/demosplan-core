/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import AdminLayerList from '@DpJs/components/map/admin/AdminLayerList'
import LayersStore from '@DpJs/store/map/Layers'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

import Vuex from 'vuex'

describe('AdminLayerList', () => {
  let store
  let mutations
  let actions

  beforeEach(() => {
    mutations = {
      setDraggableOptions: jest.fn(),
      setDraggableOptionsForBaseLayer: jest.fn(),
      setMinimapBaseLayer: jest.fn()
    }

    actions = {
      get: jest.fn(),
      setDraggableOptions: jest.fn(),
      setListOrder: jest.fn(),
      resetOrder: jest.fn(),
      save: jest.fn()
    }

    store = new Vuex.Store({
      modules: {
        Layers: {
          namespaced: true,
          state: LayersStore.state,
          mutations,
          actions,
          getters: LayersStore.getters
        }
      }
    })
  })

  it('should have the correct prop-values', () => {
    const wrapper = shallowMountWithGlobalMocks(AdminLayerList, {
      propsData: {
        procedureId: 'some-id'
      },
      store
    })

    expect(wrapper.props().procedureId).toBe('some-id')
  })

  it('should render a empty admin layer list', () => {
    const wrapper = shallowMountWithGlobalMocks(AdminLayerList, {
      propsData: {
        procedureId: 'some-id'
      },
      store
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})
