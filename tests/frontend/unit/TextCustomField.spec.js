/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { afterEach, beforeEach, describe, expect, it } from '@jest/globals'
import { sharedEditableModeTests, sharedEmptyValueTests, sharedReadonlyModeTests } from './helpers/customFieldSharedTests'
import { enableAutoUnmount } from '@vue/test-utils'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import TextCustomField from '@DpJs/components/customFields/TextCustomField'

describe('TextCustomField', () => {
  let wrapper

  /*
   * field.value is always the output of CustomField.transformValueForRenderer(),
   * never the raw backend value. Renderer components always receive the prepared form.
   */
  const defaultField = {
    id: 'field-1',
    attributes: {
      name: 'Comment',
      description: '',
      isRequired: false,
    },
    value: {
      id: 'field-1',
      value: 'Some text content',
    },
  }

  const requiredField = {
    ...defaultField,
    attributes: { ...defaultField.attributes, isRequired: true },
  }

  enableAutoUnmount(afterEach)

  describe('readonly mode with label (showLabel=true)', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: defaultField },
      })
    })

    it('should render a definition list', () => {
      expect(wrapper.find('dl').exists()).toBe(true)
    })

    it('should display the field name in dt', () => {
      expect(wrapper.find('dt').text()).toContain('Comment')
    })

    it('should display the text value in dd', () => {
      expect(wrapper.find('dd').text()).toContain('Some text content')
    })

    it('should not render DpTextArea', () => {
      expect(wrapper.findComponent({ name: 'DpTextArea' }).exists()).toBe(false)
    })

    sharedReadonlyModeTests(TextCustomField, defaultField)
  })

  describe('readonly mode without label (showLabel=false)', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: defaultField, showLabel: false },
      })
    })

    it('should not render a definition list', () => {
      expect(wrapper.find('dl').exists()).toBe(false)
    })

    it('should still display the text value', () => {
      expect(wrapper.find('span').text()).toContain('Some text content')
    })

    it('should show a dash when value is empty', () => {
      const emptyField = {
        ...defaultField,
        value: { id: 'field-1', value: '' },
      }
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: emptyField, showLabel: false },
      })

      expect(wrapper.find('span').text()).toContain('-')
    })
  })

  describe('readonly mode – empty value', () => {
    it('should show a dash when field.value.value is an empty string', () => {
      const emptyField = {
        ...defaultField,
        value: { id: 'field-1', value: '' },
      }
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: emptyField },
      })

      expect(wrapper.find('dd').text()).toContain('-')
    })

    sharedEmptyValueTests(TextCustomField, defaultField)
  })

  describe('editable mode', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: defaultField, mode: 'editable' },
      })
    })

    it('should render DpTextArea with the current value', () => {
      const textarea = wrapper.findComponent({ name: 'DpTextArea' })

      expect(textarea.exists()).toBe(true)
      expect(textarea.props('value')).toBe('Some text content')
    })

    it('should pass isRequired to DpLabel', () => {
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: requiredField, mode: 'editable' },
      })

      expect(wrapper.findComponent({ name: 'DpLabel' }).props('required')).toBe(true)
    })

    sharedEditableModeTests(TextCustomField, defaultField)
  })

  describe('update:value emit', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(TextCustomField, {
        props: { field: defaultField, mode: 'editable' },
      })
    })

    it('should emit the text string when a value is entered', () => {
      wrapper.vm.handleUpdate('New text')

      expect(wrapper.emitted('update:value')).toBeTruthy()
      expect(wrapper.emitted('update:value')[0][0]).toEqual({
        id: 'field-1',
        value: 'New text',
      })
    })

    it('should emit null when the value is cleared', () => {
      wrapper.vm.handleUpdate('')

      expect(wrapper.emitted('update:value')[0][0]).toEqual({
        id: 'field-1',
        value: null,
      })
    })
  })
})
