/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { afterEach, beforeEach, describe, expect, it } from '@jest/globals'
import { enableAutoUnmount } from '@vue/test-utils'
import SingleselectCustomField from '@DpJs/components/customFields/SingleselectCustomField'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('SingleselectCustomField', () => {
  let wrapper

  /*
   * field.value is always the output of CustomField.transformValueForRenderer(),
   * never the raw backend value. Renderer components always receive the prepared form.
   */
  const defaultField = {
    id: 'field-1',
    attributes: {
      name: 'Status',
      description: '',
      isRequired: false,
      options: [
        { id: 'opt-1', label: 'Active' },
        { id: 'opt-2', label: 'Inactive' },
      ],
    },
    value: {
      id: 'field-1',
      value: 'opt-1',
      selectedOptions: [{ id: 'opt-1', label: 'Active' }],
    },
  }

  enableAutoUnmount(afterEach)

  describe('readonly mode', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: defaultField },
      })
    })

    it('should display the field name in dt', () => {
      expect(wrapper.find('dt').text()).toContain('Status')
    })

    it('should display the selected option label in dd', () => {
      expect(wrapper.find('dd').text()).toContain('Active')
    })

    it('should show a required marker when isRequired is true', () => {
      const requiredField = {
        ...defaultField,
        attributes: { ...defaultField.attributes, isRequired: true },
      }
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: requiredField },
      })

      expect(wrapper.find('dt').text()).toContain('*')
    })

    it('should not show a required marker when isRequired is false', () => {
      expect(wrapper.find('dt').text()).not.toContain('*')
    })

    it('should render DpContextualHelp when description is set', () => {
      const fieldWithDesc = {
        ...defaultField,
        attributes: { ...defaultField.attributes, description: 'Help text' },
      }
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: fieldWithDesc },
      })

      expect(wrapper.findComponent({ name: 'DpContextualHelp' }).exists()).toBe(true)
    })

    it('should not render DpContextualHelp when description is empty', () => {
      expect(wrapper.findComponent({ name: 'DpContextualHelp' }).exists()).toBe(false)
    })

    it('should not render DpMultiselect', () => {
      expect(wrapper.findComponent({ name: 'DpMultiselect' }).exists()).toBe(false)
    })
  })

  describe('readonly mode – empty value', () => {
    it('should show a dash when selectedOptions is empty', () => {
      const emptyField = {
        ...defaultField,
        value: { id: 'field-1', value: null, selectedOptions: [] },
      }
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: emptyField },
      })

      expect(wrapper.find('dd').text()).toContain('-')
    })

    it('should show a dash when field.value is null', () => {
      const nullValueField = {
        ...defaultField,
        value: null,
      }
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: nullValueField },
      })

      expect(wrapper.find('dd').text()).toContain('-')
    })
  })

  describe('editable mode', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: defaultField, mode: 'editable' },
      })
    })

    it('should render DpMultiselect with the correct options and current value', () => {
      const multiselect = wrapper.findComponent({ name: 'DpMultiselect' })

      expect(multiselect.exists()).toBe(true)
      expect(multiselect.props('options')).toEqual(defaultField.attributes.options)
      expect(multiselect.props('value')).toEqual({ id: 'opt-1', label: 'Active' })
    })

    it('should render DpLabel when showLabel is true', () => {
      expect(wrapper.findComponent({ name: 'DpLabel' }).exists()).toBe(true)
    })

    it('should not render DpLabel when showLabel is false', () => {
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: defaultField, mode: 'editable', showLabel: false },
      })

      expect(wrapper.findComponent({ name: 'DpLabel' }).exists()).toBe(false)
    })

    it('should pass isRequired to DpMultiselect and DpLabel', () => {
      const requiredField = {
        ...defaultField,
        attributes: { ...defaultField.attributes, isRequired: true },
      }
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: requiredField, mode: 'editable' },
      })

      expect(wrapper.findComponent({ name: 'DpMultiselect' }).props('required')).toBe(true)
      expect(wrapper.findComponent({ name: 'DpLabel' }).props('required')).toBe(true)
    })

    it('should not render a definition list', () => {
      expect(wrapper.find('dl').exists()).toBe(false)
    })
  })

  describe('update:value emit', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(SingleselectCustomField, {
        props: { field: defaultField, mode: 'editable' },
      })
    })

    it('should emit the option ID as a string when a value is selected', () => {
      wrapper.vm.handleUpdate({ id: 'opt-2', label: 'Inactive' })

      expect(wrapper.emitted('update:value')).toBeTruthy()
      expect(wrapper.emitted('update:value')[0][0]).toEqual({
        id: 'field-1',
        value: 'opt-2',
      })
    })

    it('should emit null when the value is cleared', () => {
      wrapper.vm.handleUpdate(null)

      expect(wrapper.emitted('update:value')[0][0]).toEqual({
        id: 'field-1',
        value: null,
      })
    })
  })
})
