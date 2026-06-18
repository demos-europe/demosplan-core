/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { expect, it } from '@jest/globals'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

export const sharedReadonlyModeTests = (Component, defaultField) => {
  const fieldWithDesc = {
    ...defaultField,
    attributes: { ...defaultField.attributes, description: 'Help text' },
  }

  it('should show a required marker when isRequired is true', () => {
    const requiredField = {
      ...defaultField,
      attributes: { ...defaultField.attributes, isRequired: true },
    }
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: requiredField },
    })

    expect(wrapper.find('dt').text()).toContain('*')
  })

  it('should not show a required marker when isRequired is false', () => {
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: defaultField },
    })

    expect(wrapper.find('dt').text()).not.toContain('*')
  })

  it('should render DpContextualHelp when description is set', () => {
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: fieldWithDesc },
    })

    expect(wrapper.findComponent({ name: 'DpContextualHelp' }).exists()).toBe(true)
  })

  it('should not render DpContextualHelp when description is empty', () => {
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: defaultField },
    })

    expect(wrapper.findComponent({ name: 'DpContextualHelp' }).exists()).toBe(false)
  })
}

export const sharedEmptyValueTests = (Component, defaultField) => {
  it('should show a dash when field.value is null', () => {
    const nullValueField = {
      ...defaultField,
      value: null,
    }
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: nullValueField },
    })

    expect(wrapper.find('dd').text()).toContain('-')
  })
}

export const sharedEditableModeTests = (Component, defaultField) => {
  it('should render DpLabel when showLabel is true', () => {
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: defaultField, mode: 'editable' },
    })

    expect(wrapper.findComponent({ name: 'DpLabel' }).exists()).toBe(true)
  })

  it('should not render DpLabel when showLabel is false', () => {
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: defaultField, mode: 'editable', showLabel: false },
    })

    expect(wrapper.findComponent({ name: 'DpLabel' }).exists()).toBe(false)
  })

  it('should not render a definition list', () => {
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: defaultField, mode: 'editable' },
    })

    expect(wrapper.find('dl').exists()).toBe(false)
  })
}

export const sharedMultiselectBasedEditableModeTests = (Component, defaultField) => {
  it('should pass isRequired to DpMultiselect and DpLabel', () => {
    const requiredField = {
      ...defaultField,
      attributes: { ...defaultField.attributes, isRequired: true },
    }
    const wrapper = shallowMountWithGlobalMocks(Component, {
      props: { field: requiredField, mode: 'editable' },
    })

    expect(wrapper.findComponent({ name: 'DpMultiselect' }).props('required')).toBe(true)
    expect(wrapper.findComponent({ name: 'DpLabel' }).props('required')).toBe(true)
  })
}
