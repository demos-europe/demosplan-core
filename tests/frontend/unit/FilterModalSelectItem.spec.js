/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue, mount } from '@vue/test-utils'
import FilterModalSelectItem from '@DemosPlanStatementBundle/components/assessmentTable/FilterModalSelectItem'

describe('FilterModalSelectItem', () => {
  it('should be an object', () => {
    expect(typeof FilterModalSelectItem).toBe('object')
  })

  it('should be named dp-modal', () => {
    expect(FilterModalSelectItem.name).toBe('DpFilterModalSelectItem')
  })
})
