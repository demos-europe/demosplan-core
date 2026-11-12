/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { flushPromises, mount } from '@vue/test-utils'
import StatementMetaCustomFields from '@DpJs/components/procedure/StatementSegmentsList/StatementMeta/StatementMetaCustomFields.vue'

const { updateCustomFields } = vi.hoisted(() => ({
  updateCustomFields: vi.fn(),
}))

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({ updateCustomFields }),
}))

const SAVE_BUTTON = '[data-cy="statementMetaCustomFields:buttonRow:saveButton"]'
const ABORT_BUTTON = '[data-cy="statementMetaCustomFields:buttonRow:abortButton"]'

const fieldsWithDefinitions = [
  { field: { id: 'field-1', value: null }, definition: { id: 'field-1' } },
  { field: { id: 'field-2', value: 'initial' }, definition: { id: 'field-2' } },
]

const loadedValues = [
  { id: 'field-1', value: null },
  { id: 'field-2', value: 'initial' },
]

// Renders the default slot with the fields and reports the initial values like the real list does
const CustomFieldsListStub = {
  name: 'CustomFieldsList',
  props: ['definitionSourceId', 'resourceId', 'resourceType', 'sourceEntity', 'targetEntity'],
  emits: ['loaded'],
  data: () => ({ fieldsWithDefinitions }),
  mounted () {
    this.$emit('loaded', loadedValues)
  },
  template: '<div><slot :fieldsWithDefinitions="fieldsWithDefinitions" /></div>',
}

const CustomFieldStub = {
  name: 'CustomField',
  props: ['definition', 'fieldData', 'resourceId', 'resourceType'],
  emits: ['update:value'],
  template: '<div data-cy="customField" />',
}

// The button row of the library is not under test; the stub keeps its data-cy naming and events
const DpButtonRowStub = {
  name: 'DpButtonRow',
  props: ['busy', 'dataCy', 'disabled'],
  emits: ['primary-action', 'secondary-action'],
  template: `<div>
    <button :data-cy="dataCy + ':abortButton'" :disabled="disabled" @click="$emit('secondary-action')" />
    <button :data-cy="dataCy + ':saveButton'" :disabled="disabled" @click="$emit('primary-action')" />
  </div>`,
}

const mountComponent = (props = {}) => mount(StatementMetaCustomFields, {
  props: {
    editable: true,
    procedureId: 'procedure-1',
    statementId: 'statement-1',
    ...props,
  },
  global: {
    stubs: {
      CustomField: CustomFieldStub,
      CustomFieldsList: CustomFieldsListStub,
      DpButtonRow: DpButtonRowStub,
    },
  },
})

const changeFirstField = async (wrapper: ReturnType<typeof mountComponent>) => {
  wrapper.findAllComponents({ name: 'CustomField' })[0].vm.$emit('update:value', ['option-1'])
  await wrapper.vm.$nextTick()
}

const isDisabled = (wrapper: ReturnType<typeof mountComponent>, selector: string) =>
  (wrapper.find(selector).element as HTMLButtonElement).disabled

describe('StatementMetaCustomFields', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    updateCustomFields.mockResolvedValue({})
  })

  it('renders the legend and one custom field per definition', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('legend').text()).toBe('fields.configurable')
    expect(wrapper.findAll('[data-cy="customField"]')).toHaveLength(2)
  })

  it('loads the custom fields of the statement from the procedure', () => {
    const wrapper = mountComponent()

    expect(wrapper.findComponent({ name: 'CustomFieldsList' }).props()).toMatchObject({
      definitionSourceId: 'procedure-1',
      resourceId: 'statement-1',
      resourceType: 'Statement',
      sourceEntity: 'PROCEDURE',
      targetEntity: 'STATEMENT',
    })
  })

  it('does not render the buttons if the statement is not editable', () => {
    const wrapper = mountComponent({ editable: false })

    expect(wrapper.find(SAVE_BUTTON).exists()).toBe(false)
    expect(wrapper.find(ABORT_BUTTON).exists()).toBe(false)
  })

  it('disables both buttons as long as nothing has changed', () => {
    const wrapper = mountComponent()

    expect(isDisabled(wrapper, SAVE_BUTTON)).toBe(true)
    expect(isDisabled(wrapper, ABORT_BUTTON)).toBe(true)
  })

  it('enables both buttons after a value was changed', async () => {
    const wrapper = mountComponent()

    await changeFirstField(wrapper)

    expect(isDisabled(wrapper, SAVE_BUTTON)).toBe(false)
    expect(isDisabled(wrapper, ABORT_BUTTON)).toBe(false)
  })

  it('saves all values of the statement and disables the buttons again', async () => {
    const wrapper = mountComponent()

    await changeFirstField(wrapper)
    await wrapper.find(SAVE_BUTTON).trigger('click')
    await flushPromises()

    expect(updateCustomFields).toHaveBeenCalledWith('Statement', 'statement-1', [
      { id: 'field-1', value: ['option-1'] },
      { id: 'field-2', value: 'initial' },
    ])
    expect(dplan.notify.notify).toHaveBeenCalledWith('confirm', 'confirm.saved')
    expect(isDisabled(wrapper, SAVE_BUTTON)).toBe(true)
  })

  it('restores the initial values when aborting', async () => {
    const wrapper = mountComponent()

    await changeFirstField(wrapper)
    await wrapper.find(ABORT_BUTTON).trigger('click')

    expect(wrapper.findAllComponents({ name: 'CustomField' })[0].props('fieldData')).toEqual({
      id: 'field-1',
      value: null,
    })
    expect(isDisabled(wrapper, SAVE_BUTTON)).toBe(true)
    expect(updateCustomFields).not.toHaveBeenCalled()
  })

  it('shows an error and keeps the changes if saving fails', async () => {
    updateCustomFields.mockRejectedValue(new Error('failed'))
    const wrapper = mountComponent()

    await changeFirstField(wrapper)
    await wrapper.find(SAVE_BUTTON).trigger('click')
    await flushPromises()

    expect(dplan.notify.error).toHaveBeenCalledWith('error.api.generic')
    expect(isDisabled(wrapper, SAVE_BUTTON)).toBe(false)
  })
})
