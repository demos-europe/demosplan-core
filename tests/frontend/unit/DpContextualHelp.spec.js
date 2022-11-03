/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import DpContextualHelp from '@DemosPlanCoreBundle/components/DpContextualHelp'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('DpContextualHelp', () => {
  it('should be an object', () => {
    expect(typeof DpContextualHelp).toBe('object')
  })

  it('should be named DpContextualHelp', () => {
    expect(DpContextualHelp.name).toBe('DpContextualHelp')
  })

  it('should render the correct html', async () => {
    const localVue = createLocalVue()

    const instance = shallowMountWithGlobalMocks(DpContextualHelp, {
      propsData: {
        text: 'This is the tooltip content.'
      },
      localVue
    })

    expect(instance.html()).toMatchSnapshot()
  })
})
