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
import CustomField from '@DpJs/components/customFields/CustomField'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('CustomField', () => {
  let wrapper

  /*
   * Pass definition directly to bypass the composable's API fetch.
   * enrichFieldValue runs for real (pure function) — no mocking needed.
   */
  const singleSelectDefinition = {
    id: 'field-1',
    attributes: {
      name: 'Status',
      description: '',
      isRequired: false,
      fieldType: 'singleSelect',
      options: [
        { id: 'opt-1', label: 'Active' },
        { id: 'opt-2', label: 'Inactive' },
      ],
    },
  }

  const multiSelectDefinition = {
    id: 'field-1',
    attributes: {
      name: 'Tags',
      description: '',
      isRequired: false,
      fieldType: 'multiSelect',
      options: [
        { id: 'a', label: 'A' },
        { id: 'b', label: 'B' },
      ],
    },
  }

  const textDefinition = {
    id: 'field-1',
    attributes: {
      name: 'Comment',
      description: '',
      isRequired: false,
      fieldType: 'text',
    },
  }

  const singleSelectFieldData = { id: 'field-1', value: 'opt-1' }
  const multiSelectFieldData = { id: 'field-1', value: ['a'] }
  const textFieldData = { id: 'field-1', value: 'Some text' }

  enableAutoUnmount(afterEach)

  describe('type dispatch – direct mode', () => {
    it('should render SingleselectCustomField for singleSelect fieldType', () => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
        },
      })

      expect(wrapper.findComponent({ name: 'SingleselectCustomField' }).exists()).toBe(true)
    })

    it('should render MultiselectCustomField for multiSelect fieldType', () => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: multiSelectDefinition,
          fieldData: multiSelectFieldData,
        },
      })

      expect(wrapper.findComponent({ name: 'MultiselectCustomField' }).exists()).toBe(true)
    })

    it('should render TextCustomField for text fieldType', () => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: textDefinition,
          fieldData: textFieldData,
        },
      })

      expect(wrapper.findComponent({ name: 'TextCustomField' }).exists()).toBe(true)
    })

    it('should render nothing when no definition is provided', () => {
      /*
       * Intentionally mounting without definition — the component warns in created() by design.
       * The warning is suppressed here to keep the test output clean.
       */
      const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {})

      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: { fieldData: singleSelectFieldData },
      })

      warnSpy.mockRestore()

      expect(wrapper.find('div').exists()).toBe(false)
    })
  })

  describe('direct mode', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
        },
      })
    })

    it('should pass update:value from child to parent with the value unwrapped', () => {
      wrapper.vm.handleValueUpdate({ id: 'field-1', value: 'opt-2' })

      expect(wrapper.emitted('update:value')).toBeTruthy()
      expect(wrapper.emitted('update:value')[0][0]).toBe('opt-2')
    })
  })

  describe('toggle mode – readonly state', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
          enableToggle: true,
        },
      })
    })

    it('should render the renderer in readonly mode', () => {
      const renderer = wrapper.findComponent({ name: 'SingleselectCustomField' })

      expect(renderer.exists()).toBe(true)
      expect(renderer.props('mode')).toBe('readonly')
    })

    it('should render an edit button', () => {
      const icons = wrapper.findAllComponents({ name: 'DpButton' }).map(b => b.props('icon'))

      expect(icons).toContain('edit')
    })

    it('should not render save or cancel buttons', () => {
      const icons = wrapper.findAllComponents({ name: 'DpButton' }).map(b => b.props('icon'))

      expect(icons).not.toContain('check')
      expect(icons).not.toContain('x')
    })
  })

  describe('toggle mode – after startEditing', () => {
    beforeEach(async () => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
          enableToggle: true,
        },
      })
      wrapper.vm.startEditing()
      await wrapper.vm.$nextTick()
    })

    it('should render the renderer in editable mode', () => {
      const renderer = wrapper.findComponent({ name: 'SingleselectCustomField' })

      expect(renderer.exists()).toBe(true)
      expect(renderer.props('mode')).toBe('editable')
    })

    it('should render save and cancel buttons', () => {
      const icons = wrapper.findAllComponents({ name: 'DpButton' }).map(b => b.props('icon'))

      expect(icons).toContain('check')
      expect(icons).toContain('x')
    })

    it('should not render the edit button', () => {
      const icons = wrapper.findAllComponents({ name: 'DpButton' }).map(b => b.props('icon'))

      expect(icons).not.toContain('edit')
    })

    it('should emit edit:start', () => {
      expect(wrapper.emitted('edit:start')).toBeTruthy()
    })
  })

  describe('toggle mode – handleEditingValueUpdate', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
          enableToggle: true,
        },
      })
      wrapper.vm.startEditing()
    })

    it('should store the incoming value in editingValue', () => {
      wrapper.vm.handleEditingValueUpdate({ id: 'field-1', value: 'opt-2' })

      expect(wrapper.vm.editingValue).toBe('opt-2')
    })

    it('should not emit update:value during editing', () => {
      wrapper.vm.handleEditingValueUpdate({ id: 'field-1', value: 'opt-2' })

      expect(wrapper.emitted('update:value')).toBeFalsy()
    })
  })

  describe('toggle mode – cancelEdit', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
          enableToggle: true,
        },
      })
      wrapper.vm.startEditing()
    })

    it('should return to readonly mode', () => {
      wrapper.vm.cancelEdit()

      expect(wrapper.vm.isEditing).toBe(false)
    })

    it('should emit edit:cancel', () => {
      wrapper.vm.cancelEdit()

      expect(wrapper.emitted('edit:cancel')).toBeTruthy()
    })

    it('should not emit update:value', () => {
      wrapper.vm.cancelEdit()

      expect(wrapper.emitted('update:value')).toBeFalsy()
    })
  })

  describe('toggle mode – saveEdit without API', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
          enableToggle: true,
        },
      })
      wrapper.vm.startEditing()
    })

    it('should emit update:value with the editing value', () => {
      wrapper.vm.saveEdit()

      expect(wrapper.emitted('update:value')).toBeTruthy()
      expect(wrapper.emitted('update:value')[0][0]).toBe('opt-1')
    })

    it('should emit edit:save with the editing value', () => {
      wrapper.vm.saveEdit()

      expect(wrapper.emitted('edit:save')).toBeTruthy()
      expect(wrapper.emitted('edit:save')[0][0]).toBe('opt-1')
    })

    it('should return to readonly mode after saving', () => {
      wrapper.vm.saveEdit()

      expect(wrapper.vm.isEditing).toBe(false)
    })
  })

  describe('toggle mode – required field validation', () => {
    it('should block save when a required field has a null value', () => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: {
            ...singleSelectDefinition,
            attributes: { ...singleSelectDefinition.attributes, isRequired: true },
          },
          fieldData: { id: 'field-1', value: null },
          enableToggle: true,
          resourceType: 'Statement',
          resourceId: 'res-1',
        },
      })
      wrapper.vm.startEditing()
      wrapper.vm.saveEdit()

      expect(wrapper.emitted('update:value')).toBeFalsy()
      expect(wrapper.emitted('edit:save')).toBeFalsy()
    })

    it('should block save when a required field has an empty array', () => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: {
            ...multiSelectDefinition,
            attributes: { ...multiSelectDefinition.attributes, isRequired: true },
          },
          fieldData: { id: 'field-1', value: [] },
          enableToggle: true,
          resourceType: 'Statement',
          resourceId: 'res-1',
        },
      })
      wrapper.vm.startEditing()
      wrapper.vm.saveEdit()

      expect(wrapper.emitted('update:value')).toBeFalsy()
      expect(wrapper.emitted('edit:save')).toBeFalsy()
    })
  })

  describe('isActiveEdit external close', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomField, {
        props: {
          definition: singleSelectDefinition,
          fieldData: singleSelectFieldData,
          enableToggle: true,
          isActiveEdit: null,
        },
      })
      wrapper.vm.startEditing()
    })

    it('should close editing mode when isActiveEdit changes to false', async () => {
      await wrapper.setProps({ isActiveEdit: false })

      expect(wrapper.vm.isEditing).toBe(false)
    })

    it('should not emit edit:cancel when closed externally', async () => {
      await wrapper.setProps({ isActiveEdit: false })

      expect(wrapper.emitted('edit:cancel')).toBeFalsy()
    })
  })
})
