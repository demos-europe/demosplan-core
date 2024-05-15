/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpProcedureCoordinateInput from '@DpJs/components/procedure/basicSettings/DpProcedureCoordinateInput'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpProcedureCoordinateInput', () => {
  let wrapper

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(DpProcedureCoordinateInput, {})
  })

  it('should match the snapshot', () => {
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('should check if coordinates are valid', () => {
    wrapper.setData({ latitudeValue: '123', longitudeValue: '456' })
    expect(wrapper.vm.isCoordinatesValid).toBe(true)

    wrapper.setData({ latitudeValue: 'abc', longitudeValue: '456' })
    expect(wrapper.vm.isCoordinatesValid).toBe(false)
  })

  it('should update coordinates correctly', () => {
    wrapper.vm.updateCoordinates(['789', '012'])
    expect(wrapper.vm.latitudeValue).toBe('789')
    expect(wrapper.vm.longitudeValue).toBe('012')
  })

  it('should convert string to float correctly', () => {
    expect(wrapper.vm.convertToFloat('123.456')).toBe(123.456)
    expect(wrapper.vm.convertToFloat('123,456')).toBe(123.456)
  })

  it('should enable the button with valid and disable with invalid input from props', async () => {
    await wrapper.setProps({ coordinate: ['123', '456'] })
    expect(wrapper.html()).not.toContain('disabled="disabled"')

    await wrapper.setProps({ coordinate: ['abc', '456'] })
    expect(wrapper.html()).toContain('disabled="disabled"')
  })
})
