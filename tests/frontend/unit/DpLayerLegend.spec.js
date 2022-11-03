/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpLayerLegend from '@DemosPlanMapBundle/components/publicdetail/controls/legendList/DpLayerLegend'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('DpLayerLegend', () => {
  it('should be an object', () => {
    expect(typeof DpLayerLegend).toBe('object')
  })

  it('should be named DpLayerLegend', () => {
    expect(DpLayerLegend.name).toBe('DpLayerLegend')
  })

  it('should return an element array for the sidebar', () => {
    const wrapper = shallowMountWithGlobalMocks(DpLayerLegend, {
      propsData: {
        displayLegendBox: true
      },
      computed: {
        elementListForLegendSidebar: () => jest.fn().mockReturnValue(['a', 'b', 'c'])
      }
    })

    expect(wrapper.vm.elementListForLegendSidebar()).toEqual(['a', 'b', 'c'])
  })
})
