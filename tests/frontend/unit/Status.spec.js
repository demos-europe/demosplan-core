/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import Status from '@DpJs/components/statement/fragment/Status'

describe('Status', () => {
  it('should be an object', () => {
    expect(typeof Status).toBe('object')
  })

  it('should be named dp-fragment-status', () => {
    expect(Status.name).toBe('DpFragmentStatus')
  })
})
