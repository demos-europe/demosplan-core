/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import DpPublicLayerList from '@DpJs/components/map/publicdetail/controls/layerlist/DpPublicLayerList'
import Vuex from 'vuex'

const localVue = createLocalVue()

const EventBusPlugin = {
  install: function (localVue, options) {
    localVue.prototype.emit = jest.fn()
    localVue.prototype.on = jest.fn()
  }
}

localVue.use(EventBusPlugin)
localVue.use(Vuex)

global.Bus = {
  emit: jest.fn(),
  on: jest.fn()
}

describe('DpPublicLayerList', () => {
  let store
  let getters

  beforeEach(() => {
    store = new Vuex.Store({
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
    const wrapper = shallowMount(DpPublicLayerList, {
      propsData: {
        layers: [],
        unfolded: false,
        layerType: 'overlay',
        layerGroupsAlternateVisibility: true
      },
      store
    })

    expect(typeof wrapper.props().layers).toBe('object')
    expect(wrapper.props().unfolded).toBe(false)
    expect(wrapper.props().layerType).toBe('overlay')
    expect(wrapper.props().layerGroupsAlternateVisibility).toBe(true)
  })
})
