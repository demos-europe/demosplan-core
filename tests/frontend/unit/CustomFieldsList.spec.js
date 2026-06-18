/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * Mock useCustomFields so fetchCustomFieldsData resolves silently.
 * Tests inject definitions and values directly via setData instead of relying on the API.
 */
jest.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({
    fetchCustomFields: jest.fn().mockResolvedValue([]),
    fetchCustomFieldValues: jest.fn().mockResolvedValue([]),
    getCustomFieldsDefinitions: jest.fn().mockReturnValue(null),
    hasCachedValues: jest.fn().mockReturnValue(false),
  }),
}))

import { afterEach, beforeEach, describe, expect, it } from '@jest/globals'
import { enableAutoUnmount } from '@vue/test-utils'
import CustomFieldsList from '@DpJs/components/customFields/CustomFieldsList'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('CustomFieldsList', () => {
  let wrapper

  /*
   * expandable=true + batchFilterPath=null causes mounted() to return early
   * without calling fetchCustomFieldsData, so tests can inject data freely via setData.
   */
  const defaultProps = {
    batchFilterPath: null,
    definitionSourceId: 'def-source-1',
    expandable: true,
    resourceId: 'res-1',
    resourceType: 'Statement',
  }

  const singleSelectDefinition = { id: 'field-1', attributes: { fieldType: 'singleSelect', name: 'Status', options: [] } }
  const multiSelectDefinition = { id: 'field-2', attributes: { fieldType: 'multiSelect', name: 'Tags', options: [] } }

  enableAutoUnmount(afterEach)

  describe('fieldsToRender computed', () => {
    describe('when showEmpty is true', () => {
      beforeEach(() => {
        wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
          props: { ...defaultProps, showEmpty: true },
        })
      })

      it('should include fields that have no matching value', async () => {
        await wrapper.setData({
          definitions: [singleSelectDefinition, multiSelectDefinition],
          values: [{ id: 'field-1', value: 'opt-1' }],
        })

        const result = wrapper.vm.fieldsToRender

        expect(result).toHaveLength(2)
        expect(result[1].value).toBeNull()
      })

      it('should include fields with an empty string value', async () => {
        await wrapper.setData({ definitions: [singleSelectDefinition], values: [{ id: 'field-1', value: '' }] })

        expect(wrapper.vm.fieldsToRender).toHaveLength(1)
      })
    })

    describe('when showEmpty is false', () => {
      beforeEach(() => {
        wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
          props: defaultProps,
        })
      })

      it('should filter out a field that has no matching value', async () => {
        await wrapper.setData({ definitions: [singleSelectDefinition], values: [] })

        expect(wrapper.vm.fieldsToRender).toHaveLength(0)
      })

      it('should filter out a field with an empty string value', async () => {
        await wrapper.setData({ definitions: [singleSelectDefinition], values: [{ id: 'field-1', value: '' }] })

        expect(wrapper.vm.fieldsToRender).toHaveLength(0)
      })

      it('should filter out a field with an empty array value', async () => {
        await wrapper.setData({ definitions: [multiSelectDefinition], values: [{ id: 'field-2', value: [] }] })

        expect(wrapper.vm.fieldsToRender).toHaveLength(0)
      })

      it('should include a field with a non-empty value', async () => {
        await wrapper.setData({ definitions: [singleSelectDefinition], values: [{ id: 'field-1', value: 'opt-1' }] })

        const result = wrapper.vm.fieldsToRender

        expect(result).toHaveLength(1)
        expect(result[0]).toEqual({ id: 'field-1', value: 'opt-1' })
      })
    })
  })

  describe('handleValueUpdate method', () => {
    beforeEach(async () => {
      wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
        props: defaultProps,
      })
      await wrapper.setData({ values: [{ id: 'field-1', value: 'opt-1' }] })
    })

    it('should append a new entry when the field id is not yet in values', () => {
      wrapper.vm.handleValueUpdate('field-2', 'opt-a')

      expect(wrapper.vm.values).toHaveLength(2)
      expect(wrapper.vm.values[1]).toEqual({ id: 'field-2', value: 'opt-a' })
    })

    it('should immutably update an existing entry without mutating the original array reference', () => {
      const originalValues = wrapper.vm.values

      wrapper.vm.handleValueUpdate('field-1', 'opt-2')

      expect(wrapper.vm.values[0].value).toBe('opt-2')
      expect(wrapper.vm.values).not.toBe(originalValues)
    })

    it('should emit update:value with the correct fieldId and value', () => {
      wrapper.vm.handleValueUpdate('field-1', 'opt-2')

      expect(wrapper.emitted('update:value')).toBeTruthy()
      expect(wrapper.emitted('update:value')[0][0]).toEqual({ fieldId: 'field-1', value: 'opt-2' })
    })
  })

  describe('getIsActiveEdit method', () => {
    it('should return null when enableToggle is false', () => {
      wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
        props: { ...defaultProps, enableToggle: false },
      })

      expect(wrapper.vm.getIsActiveEdit('field-1')).toBeNull()
    })

    it('should return true when enableToggle is true and no field is currently active', () => {
      wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
        props: { ...defaultProps, enableToggle: true },
      })

      expect(wrapper.vm.getIsActiveEdit('field-1')).toBe(true)
    })

    it('should return true when enableToggle is true and this field is the active one', async () => {
      wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
        props: { ...defaultProps, enableToggle: true },
      })
      await wrapper.setData({ activeEditFieldId: 'field-1' })

      expect(wrapper.vm.getIsActiveEdit('field-1')).toBe(true)
    })

    it('should return false when enableToggle is true and a different field is active', async () => {
      wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
        props: { ...defaultProps, enableToggle: true },
      })
      await wrapper.setData({ activeEditFieldId: 'field-2' })

      expect(wrapper.vm.getIsActiveEdit('field-1')).toBe(false)
    })
  })

  describe('handleEditStart and handleEditEnd methods', () => {
    beforeEach(() => {
      wrapper = shallowMountWithGlobalMocks(CustomFieldsList, {
        props: defaultProps,
      })
    })

    it('should set activeEditFieldId when handleEditStart is called', () => {
      wrapper.vm.handleEditStart('field-1')

      expect(wrapper.vm.activeEditFieldId).toBe('field-1')
    })

    it('should clear activeEditFieldId when handleEditEnd is called for the active field', () => {
      wrapper.vm.handleEditStart('field-1')
      wrapper.vm.handleEditEnd('field-1')

      expect(wrapper.vm.activeEditFieldId).toBeNull()
    })

    it('should not change activeEditFieldId when handleEditEnd is called for a different field', () => {
      wrapper.vm.handleEditStart('field-1')
      wrapper.vm.handleEditEnd('field-2')

      expect(wrapper.vm.activeEditFieldId).toBe('field-1')
    })
  })
})
