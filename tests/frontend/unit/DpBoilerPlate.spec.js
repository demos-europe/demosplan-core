/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpBoilerPlate from '@DemosPlanCoreBundle/components/tiptapComponents/DpBoilerPlate'

describe('DpBoilerPlate', () => {
  it('should be an object', () => {
    expect(typeof DpBoilerPlate).toBe('object')
  })

  it('should be named DpBoilerPlate', () => {
    expect(DpBoilerPlate.name).toBe('DpBoilerPlate')
  })
})
