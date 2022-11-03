/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpSelect from '@DpJs/components/core/form/DpSelect'
import { runBooleanAttrTests } from './shared/Attributes'
// Import { runLabelTests } from './shared/Label'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('DpSelect', () => {
  const options = [{ label: 'option1', value: 'value1' }, { label: 'option2', value: 'value2' }, { label: 'option3', value: 'value3' }]

  const wrapper = shallowMountWithGlobalMocks(DpSelect, {
    propsData: { options }
  })
  // RunLabelTests(wrapper)

  const select = wrapper.find('select')
  runBooleanAttrTests(wrapper, select, 'disabled')

  it('displays a placeholder if showPlaceholder is true', () => {
    const placeholder = 'somePlaceholder'
    const componentWrapper = shallowMountWithGlobalMocks(DpSelect, {
      propsData: {
        options,
        placeholder,
        showPlaceholder: true
      }
    })

    const placeholderOption = componentWrapper.find('option[data-id="placeholder"]')
    expect(placeholderOption.exists()).toBe(true)
    expect(placeholderOption.text()).toBe(placeholder)
  })

  it('does not display a placeholder if showPlaceholder is false', () => {
    const placeholder = 'somePlaceholder'
    const componentWrapper = shallowMountWithGlobalMocks(DpSelect, {
      propsData: {
        options,
        placeholder,
        showPlaceholder: false
      }
    })

    const placeholderOption = componentWrapper.find('option[data-id="placeholder"]')
    expect(placeholderOption.exists()).toBe(false)
  })

  it('emits an event on select with the selected value as argument', async () => {
    const componentWrapper = shallowMountWithGlobalMocks(DpSelect, {
      propsData: {
        options
      }
    })

    const selectOptions = componentWrapper.find('select').findAll('option')
    await selectOptions.at(1).setSelected()
    expect(componentWrapper.find('option:checked').element.value).toBe(options[0].value)
    expect(componentWrapper.emitted().select[0][0]).toEqual(options[0].value)
  })
})
