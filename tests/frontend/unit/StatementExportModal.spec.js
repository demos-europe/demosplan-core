import { afterEach, beforeEach, describe, expect, it } from '@jest/globals'
import { DpModal } from '@demos-europe/demosplan-ui'
import { enableAutoUnmount } from '@vue/test-utils'
import { sessionStorageMock } from './__mocks__/sessionStorage.mock'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import StatementExportModal from '@DpJs/components/statement/StatementExportModal'

describe('StatementExportModal', () => {
  Object.defineProperty(window, 'sessionStorage', {
    value: sessionStorageMock
  })

  let wrapper

  const findCheckboxes = () => {
    return {
      censoredCitizen: wrapper.find('#censoredCitizen'),
      censoredInstitution: wrapper.find('#censoredInstitution'),
      obscured: wrapper.find('#obscured')
    }
  }

  const defaultDocxHeaders = {
    col1: null,
    col2: null,
    col3: null
  }

  const defaultPayload = {
    docxHeaders: defaultDocxHeaders,
    fileNameTemplate: null,
    isCitizenDataCensored: false,
    isInstitutionDataCensored: false,
    isObscured: false
  }

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(StatementExportModal, {
      props: {
        isSingleStatementExport: false
      },
      global: {
        renderStubDefaultSlot: true,
        stubs: {
          'dp-modal': {
            template: '<div><slot /></div>',
            methods: {
              toggle: jest.fn()
            }
          }
        }
      }
    })

    window.sessionStorage.clear()
    wrapper.vm.setInitialValues()
  })

  enableAutoUnmount(afterEach)

  it('opens the modal when the button is clicked', async () => {
    const modal = wrapper.findComponent(DpModal)
    const mockEvent = { preventDefault: jest.fn() }
    modal.vm.$emit('click', mockEvent)

    expect(modal.isVisible()).toBe(true)
  })

  it('sets the initial values correctly', () => {
    const sessionStorageValue = 'Stored Column Title'
    window.sessionStorage.setItem('exportModal:docxCol:col1', JSON.stringify(sessionStorageValue))
    wrapper.vm.setInitialValues()

    expect(wrapper.vm.$data.active).toBe('docx_normal')
    expect(wrapper.vm.docxColumns.col1.title).toBe(sessionStorageValue)
    expect(wrapper.vm.docxColumns.col2.title).toBe(null)
    expect(wrapper.vm.docxColumns.col3.title).toBe(null)
  })

  it('renders input fields when export type is docx or zip', () => {
    const exportTypes = ['docx_normal', 'zip_normal']

    exportTypes.map(async exportType => {
      await wrapper.setData({ active: exportType })

      Object.keys(wrapper.vm.docxColumns).forEach(key => {
        const input = wrapper.find(`[datacy="exportModal:input:${key}"]`)
        expect(input.exists()).toBe(true)
      })
    })
  })

  it('does not render input fields when export type is not docx or zip', async () => {
    await wrapper.setData({ active: 'xlsx_normal' })
    const inputs = wrapper.findAllComponents({ name: 'DpInput' })

    expect(inputs.length).toBe(0)
  })

  it('renders checkboxes for isCitizenDataCensored, isInstitutionDataCensored and isObscure when export type is not xlsx', async () => {
    await wrapper.setData({
      active: 'docx_normal'
    })
    const { censoredCitizen, censoredInstitution, obscured } = findCheckboxes()

    expect(censoredCitizen.exists()).toBe(true)
    expect(censoredInstitution.exists()).toBe(true)
    expect(obscured.exists()).toBe(true)
  })

  it('does not render checkboxes for isCensored and isObscure when export type is xlsx', async () => {
    await wrapper.setData({ active: 'xlsx_normal' })
    const { censoredCitizen, censoredInstitution, obscured } = findCheckboxes()

    expect(censoredCitizen.exists()).toBe(false)
    expect(censoredInstitution.exists()).toBe(false)
    expect(obscured.exists()).toBe(false)
  })

  it('emits export event with initial column titles when no changes are made', () => {
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0] /** It returns an array with all the occurrences of `this.$emit('export')` */
    const payload = {
      ...defaultPayload,
      route: 'dplan_statement_segments_export',
      shouldConfirm: true
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with updated col2 title', () => {
    const docxColumns = {
      col1: { title: null },
      col2: { title: 'Test Column Title' },
      col3: { title: null }
    }
    const docxHeaders = Object.fromEntries(Object.entries(docxColumns).map(([key, value]) => [key, value.title]))

    wrapper.setData({
      docxColumns
    })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]
    const payload = {
      ...defaultPayload,
      route: 'dplan_statement_segments_export',
      docxHeaders,
      shouldConfirm: true
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with null docxHeaders for xlsx export type', () => {
    wrapper.setData({ active: 'xlsx_normal' })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]
    const payload = {
      ...defaultPayload,
      route: 'dplan_statement_xls_export',
      docxHeaders: null,
      shouldConfirm: false
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with isCitizenDataCensored true if censoredCitizen is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isCitizenDataCensored: true
    })
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]
    const payload = {
      ...defaultPayload,
      isCitizenDataCensored: true,
      route: 'dplan_statement_segments_export',
      shouldConfirm: true
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with isInstitutionDataCensored true if censoredInstitution is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isCitizenDataCensored: false,
      isInstitutionDataCensored: true
    })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: defaultDocxHeaders,
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: true,
      isObscured: false
    })
  })

  it('emits export event with isObscured true if obscured checkbox is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isObscure: true
    })
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: defaultDocxHeaders,
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: false,
      isObscured: true
    })
  })

  it('closes the DpModal after executing the handleExport function', () => {
    const toggleSpy = jest.spyOn(wrapper.vm.$refs.exportModalInner, 'toggle')
    wrapper.vm.handleExport()

    expect(toggleSpy).toHaveBeenCalled()
  })

  it('renders radio buttons when isSingleStatementExport is false', () => {
    const radioButtons = wrapper.findAllComponents({ name: 'DpRadio' })

    expect(radioButtons.length).toBe(Object.keys(wrapper.vm.exportTypes).length)
  })

  it('does not render radio buttons when isSingleStatementExport is true', async () => {
    await wrapper.setProps({ isSingleStatementExport: true })
    const radioButtons = wrapper.findAllComponents({ name: 'DpRadio' })

    expect(radioButtons.length).toBe(0)
  })
})
