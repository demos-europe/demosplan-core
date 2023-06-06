/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpLayerLegend from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegend'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpLayerLegend', () => {
  it('should be an object', () => {
    expect(typeof DpLayerLegend).toBe('object')
  })

  it('should be named DpLayerLegend', () => {
    expect(DpLayerLegend.name).toBe('DpLayerLegend')
  })
})
