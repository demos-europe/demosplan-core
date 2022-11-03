/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpTiptap from '@DemosPlanCoreBundle/components/DpTiptap'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('Tiptap', () => {
  it('currentValue should reflect the input value', () => {
    const instance = shallowMountWithGlobalMocks(DpTiptap, {
      propsData: {
        value: 'test'
      }
    })

    const tiptap = instance.vm

    expect(tiptap.currentValue).toBe('test')
  })
})
