/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { createStore } from 'vuex'
import CustomField from '@DpJs/components/customFields/CustomField.vue'
import CustomFieldsList from '@DpJs/components/customFields/CustomFieldsList.vue'
import PublicStatementStore from '@DpJs/store/statement/PublicStatement'
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal.vue'

const { mockDpApiGet, mockMakeFormPost, mockUpdateCustomFields } = vi.hoisted(() => ({
  mockDpApiGet: vi.fn(),
  mockMakeFormPost: vi.fn(),
  mockUpdateCustomFields: vi.fn(),
}))

vi.mock('@demos-europe/demosplan-ui', async importOriginal => ({
  ...(await importOriginal<Record<string, unknown>>()),
  dpApi: { get: (...args: unknown[]) => mockDpApiGet(...args) },
  makeFormPost: (...args: unknown[]) => mockMakeFormPost(...args),
}))

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({ updateCustomFields: mockUpdateCustomFields }),
}))

type FormData = Record<string, unknown>
type Wrapper = ReturnType<typeof shallowMount>

// The form lives in the modal's default slot, which a plain stub doesn't render
const DpModalStub = {
  template: '<div><slot /></div>',
  methods: { toggle: vi.fn() },
}

/*
 * Form validation checks every element with a required or pattern attribute, but stubs aren't real inputs
 * and lack setCustomValidity, so these stubs must not render their props as attributes
 */
const PlainStub = { template: '<div />', inheritAttrs: false }

const LocationGroupStub = { ...PlainStub, props: ['disabled', 'required'] }

const LabelStub = { ...PlainStub, props: ['required', 'text'] }

const resetEditor = vi.fn()
const clearFilesList = vi.fn()

const createTestStore = () => createStore({
  modules: {
    Notify: {
      namespaced: true,
      state: { messages: [] },
      mutations: { remove: vi.fn() },
    },
    PublicStatement: {
      ...PublicStatementStore,
      // The module's state holds shared object references, so every test needs its own copy
      state: () => JSON.parse(JSON.stringify(PublicStatementStore.state)),
    },
  },
})

// Mounted with attachTo, so each wrapper has to be unmounted explicitly
const mountedWrappers: Wrapper[] = []

const mountModal = async (props: Record<string, unknown> = {}) => {
  const store = createTestStore()
  const wrapper = shallowMount(StatementModal, {
    // Form validation looks the form up in the document
    attachTo: document.body,
    props: {
      loggedIn: true,
      planningDocumentsHasNegativeStatement: true,
      procedureId: 'procedure-1',
      projectName: 'project',
      ...props,
    },
    global: {
      plugins: [store],
      stubs: {
        DpCheckbox: PlainStub,
        DpEditor: { ...PlainStub, props: ['readonly', 'required'], methods: { resetEditor } },
        DpInput: PlainStub,
        DpLabel: LabelStub,
        DpModal: DpModalStub,
        DpUploadFiles: { ...PlainStub, props: ['disabled'], methods: { clearFilesList } },
        FormGroupMapReference: LocationGroupStub,
      },
    },
  })

  mountedWrappers.push(wrapper)
  await flushPromises()

  return { store, wrapper }
}

const setFormData = async (wrapper: Wrapper, data: FormData) => {
  (wrapper.vm as unknown as { setStatementData: (data: FormData) => void }).setStatementData(data)
  await wrapper.vm.$nextTick()
}

// The modal renders several inline notifications, so the hint is identified by its message
const hintIsShown = (wrapper: Wrapper) => wrapper
  .findAllComponents({ name: 'DpInlineNotification' })
  .some(notification => notification.props('message') === 'statement.negative_report.hint')

const buttonWithText = (wrapper: Wrapper, text: string) => wrapper.findAll('button').find(button => button.text().includes(text))

const isDisabled = (button: ReturnType<typeof buttonWithText>) => button?.attributes('disabled') !== undefined

// State of every control a negative report must not carry
const contentControlsState = (wrapper: Wrapper) => {
  const locationGroup = wrapper.findComponent(LocationGroupStub)
  const attachmentCheckbox = wrapper.find<HTMLInputElement>('input[name="delete_file[]"]').element
  const editor = wrapper.findComponent({ ref: 'statementEditor' })
  const textLabel = wrapper.findAllComponents(LabelStub)
    .find(label => label.props('text') === 'statement.detail.form.statement_text')

  return {
    attachmentChecked: attachmentCheckbox.checked,
    attachmentCheckbox: attachmentCheckbox.disabled,
    customFields: wrapper.findAllComponents(CustomField).every(field => field.props('disabled') === true),
    customFieldsRequired: wrapper.findAllComponents(CustomField).every(field => field.props('ignoreRequired') === false),
    documentChangeButton: isDisabled(buttonWithText(wrapper, 'document.reference.change')),
    documentDeleteButton: isDisabled(buttonWithText(wrapper, 'document.reference.delete')),
    editorReadonly: editor.props('readonly'),
    editorRequired: editor.props('required'),
    locationGroup: locationGroup.props('disabled'),
    locationRequired: locationGroup.props('required'),
    textLabelRequired: textLabel?.props('required'),
    upload: wrapper.findComponent({ ref: 'uploadFiles' }).props('disabled'),
  }
}

const mockConfirm = (confirmed: boolean) => vi.fn(() => confirmed)

const submitEvent = () => ({ preventDefault: vi.fn() })

// The form POST receives the payload as its first argument
const lastPostedPayload = (): FormData => mockMakeFormPost.mock.calls.at(-1)?.[0]

describe('StatementModal with negative report', () => {
  beforeEach(() => {
    mockDpApiGet.mockResolvedValue({ data: { data: [] } })
    mockMakeFormPost.mockResolvedValue({ status: 200, data: {} })
    mockUpdateCustomFields.mockResolvedValue({})
    window.dpconfirm = mockConfirm(true)
  })

  afterEach(() => {
    mountedWrappers.splice(0).forEach(wrapper => wrapper.unmount())
    document.body.innerHTML = ''
    vi.clearAllMocks()
    sessionStorage.clear()
    localStorage.clear()
  })

  describe('discard warning', () => {
    it.each([
      ['text', { r_text: '<p>Text</p>' }],
      ['an assigned element', { r_element_id: 'element-1' }],
      ['an assigned document', { r_document_id: 'document-1' }],
      ['a location reference', { r_location: 'county', r_county: 'county-1' }],
      ['unsaved uploads', { uploadedFiles: 'hash-1' }],
      ['attachments from earlier saves', { r_files_initial: JSON.stringify(['file.pdf:hash-1:100:application/pdf']) }],
      ['a filled custom field', { customFields: [{ id: 'field-1', value: ['option-1'] }] }],
    ])('is shown for %s', async (_, data) => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, { ...data, r_isNegativeReport: '1' })

      expect(hintIsShown(wrapper)).toBe(true)
    })

    it.each([
      ['an empty form', {}],
      ['"Kein Ortsbezug"', { r_location: 'notLocated', location_is_set: 'notLocated' }],
      ['the location placeholder of drafts without location', { r_location: 'mapLocation' }],
      ['empty custom fields', { customFields: [{ id: 'field-1', value: null }, { id: 'field-2', value: [] }] }],
    ])('is not shown for %s', async (_, data) => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, { ...data, r_isNegativeReport: '1' })

      expect(hintIsShown(wrapper)).toBe(false)
    })
  })

  describe('controls', () => {
    it('are disabled while the negative report is selected and enabled again after switching back', async () => {
      const { wrapper } = await mountModal({ statementFormFields: [{ name: 'mapAndCountyReference', required: true }] })
      const vm = wrapper.vm as unknown as { selectableCustomFields: { id: string, fieldType: string, value: unknown }[] }

      vm.selectableCustomFields = [{ id: 'field-1', fieldType: 'singleSelect', value: null }]
      await setFormData(wrapper, {
        r_element_id: 'element-1',
        r_files_initial: JSON.stringify(['file.pdf:hash-1:100:application/pdf']),
        r_isNegativeReport: '1',
      })

      expect(wrapper.findAllComponents(CustomField)).toHaveLength(1)
      expect(contentControlsState(wrapper)).toEqual({
        attachmentChecked: true,
        attachmentCheckbox: true,
        customFields: true,
        customFieldsRequired: false,
        documentChangeButton: true,
        documentDeleteButton: true,
        editorReadonly: true,
        editorRequired: false,
        locationGroup: true,
        locationRequired: false,
        textLabelRequired: false,
        upload: true,
      })

      await setFormData(wrapper, { r_isNegativeReport: '0' })

      expect(contentControlsState(wrapper)).toEqual({
        attachmentChecked: false,
        attachmentCheckbox: false,
        customFields: false,
        customFieldsRequired: true,
        documentChangeButton: false,
        documentDeleteButton: false,
        editorReadonly: false,
        editorRequired: true,
        locationGroup: false,
        locationRequired: true,
        textLabelRequired: true,
        upload: false,
      })
    })

    // Only rendered while no document is assigned, so it can't be checked together with change and delete
    it('disables assigning a document while the negative report is selected', async () => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, { r_element_id: '', r_isNegativeReport: '1' })

      expect(buttonWithText(wrapper, 'element.assign')?.exists()).toBe(true)
      expect(isDisabled(buttonWithText(wrapper, 'element.assign'))).toBe(true)

      await setFormData(wrapper, { r_isNegativeReport: '0' })

      expect(isDisabled(buttonWithText(wrapper, 'element.assign'))).toBe(false)
    })

    // Existing drafts without local changes render the self-fetching list instead of single fields
    it('disables the custom fields list of an existing draft and makes it optional', async () => {
      const { wrapper } = await mountModal()

      ;(wrapper.vm as unknown as { draftStatementId: string }).draftStatementId = 'draft-1'
      await setFormData(wrapper, { r_isNegativeReport: '1' })

      const list = () => wrapper.findComponent(CustomFieldsList)

      expect(list().props()).toMatchObject({ disabled: true, ignoreRequired: true })

      await setFormData(wrapper, { r_isNegativeReport: '0' })

      expect(list().props()).toMatchObject({ disabled: false, ignoreRequired: false })
    })
  })

  describe('saving', () => {
    it('keeps the statement and sends nothing when the confirmation is cancelled', async () => {
      window.dpconfirm = mockConfirm(false)
      const { store, wrapper } = await mountModal()

      await setFormData(wrapper, { r_text: '<p>Text</p>', r_isNegativeReport: '1' })
      ;(wrapper.vm as unknown as { sendStatement: (e: unknown) => void }).sendStatement(submitEvent())
      await flushPromises()

      expect(window.dpconfirm).toHaveBeenCalledWith('statement.negative_report.confirm')
      expect(mockMakeFormPost).not.toHaveBeenCalled()
      expect(store.state.PublicStatement.statement.r_isNegativeReport).toBe('0')
      expect(store.state.PublicStatement.statement.r_text).toBe('<p>Text</p>')
    })

    it('does not ask for confirmation when there is nothing to discard', async () => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, { r_isNegativeReport: '1' })
      ;(wrapper.vm as unknown as { sendStatement: (e: unknown) => void }).sendStatement(submitEvent())
      await flushPromises()

      expect(window.dpconfirm).not.toHaveBeenCalled()
      expect(mockMakeFormPost).toHaveBeenCalledTimes(1)
    })

    it('discards text, document, location, attachments and custom fields of a negative report', async () => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, {
        customFields: [{ id: 'field-1', value: 'value' }],
        r_document_id: 'document-1',
        r_element_id: 'element-1',
        r_files_initial: JSON.stringify(['file.pdf:hash-1:100:application/pdf']),
        r_isNegativeReport: '1',
        r_location: 'point',
        r_location_point: '{"type":"Point"}',
        r_text: '<p>Text</p>',
        uploadedFiles: 'hash-2',
      })
      ;(wrapper.vm as unknown as { sendStatement: (e: unknown) => void }).sendStatement(submitEvent())
      await flushPromises()

      const payload = lastPostedPayload()

      expect(payload).not.toHaveProperty('r_element_id')
      expect(payload).not.toHaveProperty('customFields')
      expect(payload).toMatchObject({
        location_is_set: 'notLocated',
        r_document_id: '',
        r_isNegativeReport: '1',
        r_location: 'notLocated',
        r_location_point: '',
        r_text: '',
        uploadedFiles: '',
      })
      expect(payload.delete_file).toEqual(['hash-1'])
    })

    // A reopened negative report has no content, but its empty r_element_id would still drop the category
    it('omits element and custom fields from the payload of a negative report without other input', async () => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, { customFields: [], r_element_id: '', r_isNegativeReport: '1' })
      ;(wrapper.vm as unknown as { sendStatement: (e: unknown) => void }).sendStatement(submitEvent())
      await flushPromises()

      const payload = lastPostedPayload()

      expect(payload).not.toHaveProperty('r_element_id')
      expect(payload).not.toHaveProperty('customFields')
    })

    it('empties the form after saving a negative report with other input', async () => {
      const { store, wrapper } = await mountModal()

      await setFormData(wrapper, {
        delete_file: ['hash-1'],
        r_document_id: 'document-1',
        r_element_id: 'element-1',
        r_files_initial: JSON.stringify(['file.pdf:hash-1:100:application/pdf']),
        r_isNegativeReport: '1',
        r_text: '<p>Text</p>',
        uploadedFiles: 'hash-2',
      })
      // Keeps the modal open: closing it resets the store anyway, which would hide a missing discard
      ;(wrapper.vm as unknown as { sendStatement: (e: unknown, immediateSubmit: boolean, keepModalOpen: boolean) => void })
        .sendStatement(submitEvent(), false, true)
      await flushPromises()

      // Without resetting the initial files, the old attachments would still be listed and trigger the hint again
      expect(store.state.PublicStatement.statement).toMatchObject({
        delete_file: [],
        r_document_id: '',
        r_element_id: '',
        r_files_initial: '[]',
        r_text: '',
        uploadedFiles: '',
      })
      // Fehlanzeige stays selected, so a leftover would show the warning again
      expect(hintIsShown(wrapper)).toBe(false)
      expect(resetEditor).toHaveBeenCalled()
      expect(clearFilesList).toHaveBeenCalled()
    })

    it('empties the custom fields after saving a negative report with other input', async () => {
      const { wrapper } = await mountModal()
      const vm = wrapper.vm as unknown as {
        selectableCustomFields: { id: string, fieldType: string, value: unknown }[]
        sendStatement: (e: unknown, immediateSubmit: boolean, keepModalOpen: boolean) => void
      }

      vm.selectableCustomFields = [
        { id: 'multi', fieldType: 'multiSelect', value: ['option-1'] },
        { id: 'single', fieldType: 'singleSelect', value: 'option-2' },
      ]
      await setFormData(wrapper, {
        customFields: [{ id: 'multi', value: ['option-1'] }, { id: 'single', value: 'option-2' }],
        r_isNegativeReport: '1',
      })
      vm.sendStatement(submitEvent(), false, true)
      await flushPromises()

      const renderedValues = wrapper.findAllComponents(CustomField).map(field => field.props('fieldData').value)

      expect(renderedValues).toEqual([[], null])
      expect(hintIsShown(wrapper)).toBe(false)
    })

    it('sends the statement unchanged when it is not a negative report', async () => {
      const { wrapper } = await mountModal()

      await setFormData(wrapper, { r_text: '<p>Text</p>', r_element_id: 'element-1' })
      ;(wrapper.vm as unknown as { sendStatement: (e: unknown) => void }).sendStatement(submitEvent())
      await flushPromises()

      expect(lastPostedPayload()).toMatchObject({ r_element_id: 'element-1', r_text: '<p>Text</p>' })
    })

    it('clears saved custom field values of an existing draft', async () => {
      const { wrapper } = await mountModal()
      const vm = wrapper.vm as unknown as {
        draftStatementId: string
        selectableCustomFields: { id: string, fieldType: string, value: unknown }[]
        sendStatement: (e: unknown) => void
      }

      vm.draftStatementId = 'draft-1'
      vm.selectableCustomFields = [
        { id: 'empty', fieldType: 'singleSelect', value: null },
        { id: 'multi', fieldType: 'multiSelect', value: [] },
        { id: 'single', fieldType: 'singleSelect', value: null },
      ]
      await wrapper.vm.$nextTick()
      // The list reports the values saved on the server; only those need clearing
      wrapper.findComponent(CustomFieldsList).vm.$emit('loaded', [
        { id: 'empty', value: null },
        { id: 'multi', value: ['option-1'] },
        { id: 'single', value: 'option-2' },
      ])
      await setFormData(wrapper, { r_isNegativeReport: '1' })
      vm.sendStatement(submitEvent())
      await flushPromises()

      expect(mockUpdateCustomFields).toHaveBeenCalledWith('DraftStatement', 'draft-1', [
        { id: 'multi', value: [] },
        { id: 'single', value: null },
      ])
    })
  })
})
