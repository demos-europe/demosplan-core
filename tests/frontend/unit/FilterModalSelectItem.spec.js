/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import FilterModalSelectItem from '@DpJs/components/statement/assessmentTable/FilterModalSelectItem'

describe('FilterModalSelectItem', () => {
  it('should be an object', () => {
    expect(typeof FilterModalSelectItem).toBe('object')
  })

  it('should be named dp-modal', () => {
    expect(FilterModalSelectItem.name).toBe('DpFilterModalSelectItem')
  })
})
