/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import Obscure from '@DemosPlanCoreBundle/components/Obscure'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'


/* mock hasPermission for the sake of testing */
// TODO: hasPermission should probably live in the webpack world

describe('Obscure', () => {
  it('should be an object', () => {
    expect(typeof Obscure).toBe('object')
  })

  it('should be named DpObscure', () => {
    expect(Obscure.name).toBe('DpObscure')
  })

  it('should add a class based on the', () => {
    const localVue = createLocalVue()

    window.hasPermission = () => true
    let slotInstance = shallowMountWithGlobalMocks(Obscure, {
      slots: {
        default: '<div>Slot Content</div>'
      },
      localVue: localVue
    })

    expect(slotInstance.html()).toBe('<span title="obscure.title" class="u-obscure"><div>Slot Content</div></span>')

    window.hasPermission = () => false
    slotInstance = shallowMountWithGlobalMocks(Obscure, {
      slots: {
        default: '<div>Slot Content</div>'
      },
      localVue: localVue
    })
    expect(slotInstance.html()).toBe('<span title="obscure.title" class=""><div>Slot Content</div></span>')
  })
})
