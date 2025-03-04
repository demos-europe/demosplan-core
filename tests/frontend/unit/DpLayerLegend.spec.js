/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpLayerLegend from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegend'
import LayersStore from '@DpJs/store/map/Layers'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import Vuex from 'vuex'

describe('DpLayerLegend', () => {
  let wrapper
  let store

  beforeEach(() => {
    store = new Vuex.Store({
      modules: {
        Layers: LayersStore
      }
    })

    wrapper = shallowMountWithGlobalMocks(DpLayerLegend, {
      store,
      props: {
        layersWithLegendFiles: [],
        planPdf: {},
        procedureId: '123'
      }
    })
  })

  it('should toggle unfolded state when toggle method is called', () => {
    expect(wrapper.vm.unfolded).toBe(false)
    wrapper.vm.toggle()
    expect(wrapper.vm.unfolded).toBe(true)
  })
})
