/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createApp } from 'vue'
import { createStore } from 'vuex'
import DpPublicLayerList from '@DpJs/components/map/publicdetail/controls/layerlist/DpPublicLayerList'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import { vi } from 'vitest'

const localVue = createApp({})

const EventBusPlugin = {
  install: function (app, options) {
    app.config.globalProperties.emit = vi.fn()
    app.config.globalProperties.on = vi.fn()
  },
}

localVue.use(EventBusPlugin)

global.Bus = {
  emit: vi.fn(),
  on: vi.fn(),
}

describe('DpPublicLayerList', () => {
  let store
  let getters

  beforeEach(() => {
    store = createStore({
      getters,
    })

    getters = {
      id: () => vi.fn(),
      layers: () => vi.fn(),
      rootId: () => vi.fn(),
      elementListForLayerSidebar: () => vi.fn(),
    }
  })

  it('has the correct props', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerList, {
      props: {
        layers: [],
        unfolded: false,
        layerType: 'overlay',
        layerGroupsAlternateVisibility: true,
      },
      global: {
        plugins: [store],
      },
    })

    expect(typeof wrapper.props().layers).toBe('object')
    expect(wrapper.props().unfolded).toBe(false)
    expect(wrapper.props().layerType).toBe('overlay')
    expect(wrapper.props().layerGroupsAlternateVisibility).toBe(true)
  })
})
