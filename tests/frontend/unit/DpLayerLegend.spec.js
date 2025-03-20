/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import DpLayerLegend from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegend'
import LayersStore from '@DpJs/store/map/Layers'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpLayerLegend', () => {
  let wrapper
  let store

  beforeEach(() => {
    store = createStore({
      modules: {
        Layers: LayersStore
      }
    })

    wrapper = shallowMountWithGlobalMocks(DpLayerLegend, {
      propsData: {
        layersWithLegendFiles: [],
        planPdf: {},
        procedureId: '123'
      },
      global: {
        plugins: [store]
      }
    })
  })

  it('should toggle unfolded state when toggle method is called', () => {
    expect(wrapper.vm.unfolded).toBe(false)
    wrapper.vm.toggle()
    expect(wrapper.vm.unfolded).toBe(true)
  })
})
