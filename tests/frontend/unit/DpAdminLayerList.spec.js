/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import AdminLayerList from '@DpJs/components/map/admin/AdminLayerList'
import { createStore } from 'vuex'
import LayersStore from '@DpJs/store/map/Layers'

import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('AdminLayerList', () => {
  let store
  let mutations
  let actions

  beforeEach(() => {
    mutations = {
      setMinimapBaseLayer: jest.fn(),
      updateState: jest.fn(),
    }

    actions = {
      get: jest.fn(),
      setListOrder: jest.fn(),
      resetOrder: jest.fn(),
      save: jest.fn(),
    }

    store = createStore({
      modules: {
        Layers: {
          namespaced: true,
          state: LayersStore.state,
          mutations,
          actions,
          getters: LayersStore.getters,
        },
      },
    })
  })

  it('should have the correct prop-values', () => {
    const wrapper = shallowMountWithGlobalMocks(AdminLayerList, {
      props: {
        procedureId: 'some-id',
      },
      global: {
        plugins: [store],
      },
    })

    expect(wrapper.props().procedureId).toBe('some-id')
  })

  it('should render a empty admin layer list', () => {
    const wrapper = shallowMountWithGlobalMocks(AdminLayerList, {
      props: {
        procedureId: 'some-id',
      },
      global: {
        plugins: [store],
      },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})
