/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const { createCustomFieldDefinition, updateCustomFieldDefinition } = vi.hoisted(() => ({
  createCustomFieldDefinition: vi.fn(() => Promise.resolve()),
  updateCustomFieldDefinition: vi.fn(() => Promise.resolve()),
}))

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({
    createCustomFieldDefinition,
    deleteCustomFieldDefinition: vi.fn(),
    fetchCustomFields: vi.fn(() => Promise.resolve([])),
    updateCustomFieldDefinition,
  }),
}))

const AdministrationCustomFieldsList = (await import('@DpJs/components/procedure/admin/AdministrationCustomFieldsList.vue')).default
const CreateCustomFieldForm = (await import('@DpJs/components/procedure/admin/CreateCustomFieldForm.vue')).default

const REQUIRED_PERMISSION = 'feature_statements_custom_fields_required'

const setPermission = (isEnabled) => {
  globalThis.hasPermission = vi.fn((permission) => permission !== REQUIRED_PERMISSION || isEnabled)
}

describe('Custom field "required" permission', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setPermission(true)
  })

  describe('CreateCustomFieldForm', () => {
    const mountForm = async (targetEntity) => {
      const wrapper = shallowMountWithGlobalMocks(CreateCustomFieldForm, {
        props: {
          targetOptions: { SEGMENT: 'Segments', STATEMENT: 'Statements' },
          typeOptions: [],
        },
      })

      await wrapper.setData({ isOpen: true, customField: { description: '', fieldType: '', isRequired: false, name: '', targetEntity } })

      return wrapper
    }

    it('shows the checkbox for statement fields with the permission', async () => {
      const wrapper = await mountForm('STATEMENT')

      expect(wrapper.find('#requiredCheckbox').exists()).toBe(true)
    })

    it('hides the checkbox for statement fields without the permission', async () => {
      setPermission(false)
      const wrapper = await mountForm('STATEMENT')

      expect(wrapper.find('#requiredCheckbox').exists()).toBe(false)
    })

    it('never shows the checkbox for segment fields', async () => {
      const wrapper = await mountForm('SEGMENT')

      expect(wrapper.find('#requiredCheckbox').exists()).toBe(false)
    })
  })

  describe('AdministrationCustomFieldsList', () => {
    const headerFieldNames = (context) =>
      AdministrationCustomFieldsList.computed.headerFields.call({
        hasOptionsBasedFieldTypes: true,
        newRowData: {},
        ...context,
      }).map(({ field }) => field)

    it('shows the required column on the statement tab with the permission', () => {
      expect(headerFieldNames({ canSetRequired: true, isActiveTabStatementContext: true })).toContain('isRequired')
    })

    it('hides the required column on the statement tab without the permission', () => {
      expect(headerFieldNames({ canSetRequired: false, isActiveTabStatementContext: true })).not.toContain('isRequired')
    })

    it('never shows the required column on other tabs', () => {
      expect(headerFieldNames({ canSetRequired: true, isActiveTabStatementContext: false })).not.toContain('isRequired')
    })

    it.each([
      [true, true],
      [false, false],
    ])('reads the permission for canSetRequired (%s)', (isEnabled, expected) => {
      setPermission(isEnabled)

      expect(AdministrationCustomFieldsList.computed.canSetRequired.call({})).toBe(expected)
    })

    describe('saveNewField', () => {
      const saveNewField = (canSetRequired, targetEntity = 'STATEMENT') => {
        const context = {
          activeTabId: targetEntity,
          canSetRequired,
          definitionSourceId: 'procedure-id',
          fetchCustomFields: vi.fn(),
          newFieldOptions: [{ label: 'Yes' }, { label: 'No' }],
          resetNewFieldForm: vi.fn(),
          resolvedSourceEntity: 'PROCEDURE',
          validateNamesAreUnique: () => true,
        }

        AdministrationCustomFieldsList.methods.saveNewField.call(context, {
          description: '',
          fieldType: 'singleSelect',
          isRequired: false,
          name: 'Decision',
          targetEntity,
        })

        return createCustomFieldDefinition.mock.calls[0][0]
      }

      it('sends isRequired for statement fields with the permission', () => {
        expect(saveNewField(true)).toHaveProperty('isRequired', false)
      })

      it('does not send isRequired for statement fields without the permission', () => {
        expect(saveNewField(false)).not.toHaveProperty('isRequired')
      })

      it('does not send isRequired for segment fields', () => {
        expect(saveNewField(true, 'SEGMENT')).not.toHaveProperty('isRequired')
      })
    })

    describe('saveEditedFields', () => {
      const saveEditedFields = async (canSetRequired) => {
        const definition = {
          id: 'field-id',
          attributes: { description: '', fieldType: 'singleSelect', isRequired: true, name: 'Decision', options: [{ id: 'a', label: 'Yes' }, { id: 'b', label: 'No' }] },
        }
        const context = {
          canSetRequired,
          currentTabDefinitions: [definition],
          customFieldItems: [{ id: 'field-id' }],
          definitionSourceId: 'procedure-id',
          editWarningMessage: '',
          fetchCustomFields: vi.fn(),
          newRowData: { id: 'field-id', description: '', isRequired: true, name: 'Renamed', options: definition.attributes.options },
          requestConfirmation: () => Promise.resolve(true),
          setEditMode: vi.fn(),
          validateNamesAreUnique: () => true,
        }

        AdministrationCustomFieldsList.methods.saveEditedFields.call(context)
        await vi.waitFor(() => expect(updateCustomFieldDefinition).toHaveBeenCalled())

        return updateCustomFieldDefinition.mock.calls[0][1].attributes
      }

      it('sends isRequired with the permission', async () => {
        expect(await saveEditedFields(true)).toHaveProperty('isRequired', true)
      })

      it('does not send isRequired without the permission', async () => {
        const attributes = await saveEditedFields(false)

        expect(attributes).not.toHaveProperty('isRequired')
        expect(attributes).toHaveProperty('name', 'Renamed')
      })
    })
  })
})
