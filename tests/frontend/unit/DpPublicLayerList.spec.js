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

const localVue = createApp({})

const EventBusPlugin = {
  install: function (app, options) {
    app.config.globalProperties.emit = jest.fn()
    app.config.globalProperties.on = jest.fn()
  }
}

localVue.use(EventBusPlugin)

global.Bus = {
  emit: jest.fn(),
  on: jest.fn()
}

describe('DpPublicLayerList', () => {
  let store
  let getters

  beforeEach(() => {
    store = createStore({
      getters
    })

    getters = {
      id: () => jest.fn(),
      layers: () => jest.fn(),
      rootId: () => jest.fn(),
      elementListForLayerSidebar: () => jest.fn()
    }
  })

  it('has the correct props', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerList, {
      props: {
        layers: [],
        unfolded: false,
        layerType: 'overlay',
        layerGroupsAlternateVisibility: true
      },
      global: {
        plugins: [store]
      }
    })

    expect(typeof wrapper.props().layers).toBe('object')
    expect(wrapper.props().unfolded).toBe(false)
    expect(wrapper.props().layerType).toBe('overlay')
    expect(wrapper.props().layerGroupsAlternateVisibility).toBe(true)
  })
})
