/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import CopyPasteButton from '@DpJs/components/core/CopyPasteButton'
import { createLocalVue } from '@vue/test-utils'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('CopyPasteButton', () => {
  it('is an object', () => {
    expect(typeof CopyPasteButton).toBe('object')
  })

  it('is named CopyPasteButton', () => {
    expect(CopyPasteButton.name).toBe('CopyPasteButton')
  })

  it('emits an event named `set-text` with the passed text as argument', () => {
    const localVue = createLocalVue()

    const copyContent = 'this is a test text. copy me'

    const mountedComponent = shallowMountWithGlobalMocks(CopyPasteButton, {
      propsData: {
        copyContent: copyContent,
        targetId: 'target'
      },
      localVue: localVue
    })

    mountedComponent.find('a').trigger('click')

    const setTextEvent = mountedComponent.emitted('set-text')

    expect(setTextEvent[0]).toEqual([copyContent])
  })
})
