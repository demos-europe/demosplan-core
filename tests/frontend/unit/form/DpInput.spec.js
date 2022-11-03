/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { runBooleanAttrTests, runStringAttrTests } from './shared/Attributes'
import { DpInput } from 'demosplan-ui/components'
// Import { runLabelTests } from './shared/Label'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('DpInput', () => {
  const wrapper = shallowMountWithGlobalMocks(DpInput, {
    propsData: {
      id: 'inputId'
    }
  })
  // RunLabelTests(wrapper)

  const input = wrapper.find('input[type="text"]')
  runBooleanAttrTests(wrapper, input, 'required')
  runBooleanAttrTests(wrapper, input, 'disabled')
  runBooleanAttrTests(wrapper, input, 'readonly')
  runStringAttrTests(wrapper, input, 'placeholder', 'This is a placeholder.')
  runStringAttrTests(wrapper, input, 'dataDpValidateError', 'Bitte füllen Sie alle Pflichtfelder(*) korrekt aus.', 'data-dp-validate-error')
  runStringAttrTests(wrapper, input, 'dataDpValidateIf', '#r_getEvaluation', 'data-dp-validate-if')
  runStringAttrTests(wrapper, input, 'dataDpValidateShouldEqual', 'This is a placeholder.', 'data-dp-validate-should-equal')

  it('emits an event on input with the new value as argument', async () => {
    const newValue = 'some text'
    const componentWrapper = shallowMountWithGlobalMocks(DpInput, {
      propsData: {
        id: 'inputId'
      }
    })

    const input = componentWrapper.find('input')
    input.setValue(newValue)
    expect(componentWrapper.emitted().input[0][0]).toEqual(newValue)
  })

  it('emits an event on keydown enter', () => {
    const componentWrapper = shallowMountWithGlobalMocks(DpInput, {
      propsData: {
        id: 'inputId'
      }
    })

    const input = componentWrapper.find('input')
    input.trigger('keydown.enter')
    expect(componentWrapper.emitted().enter).toBeDefined()
  })
})
