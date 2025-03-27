import { afterEach, beforeAll, beforeEach, describe, expect, it } from '@jest/globals'
import { DpModal } from '@demos-europe/demosplan-ui'
import { sessionStorageMock } from './__mocks__/sessionStorage.mock'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import StatementExportModal from '@DpJs/components/statement/StatementExportModal'

describe('StatementExportModal', () => {
  let sessionStorageValue
  let wrapper

  beforeAll(() => {
    Object.defineProperty(window, 'sessionStorage', {
      value: sessionStorageMock
    })

    sessionStorageValue = 'Stored Column Title'
    window.sessionStorage.setItem('exportModal:docxCol:col1', JSON.stringify(sessionStorageValue))
  })

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

    wrapper.vm.setInitialValues()
  })

  afterEach(() => {
    wrapper.unmount()
  })

  it('opens the modal when the button is clicked', async () => {
    const modal = wrapper.findComponent(DpModal)
    const mockEvent = { preventDefault: jest.fn() }
    modal.vm.$emit('click', mockEvent)

    expect(modal.isVisible()).toBe(true)
  })

  it('sets the initial values correctly', () => {
    expect(wrapper.vm.$data.active).toBe('docx_normal')
    expect(wrapper.vm.docxColumns.col1.title).toBe(sessionStorageValue)
    expect(wrapper.vm.docxColumns.col2.title).toBe(null)
    expect(wrapper.vm.docxColumns.col3.title).toBe(null)
  })

  it('renders input fields when export type is docx or zip', () => {
    const exportTypes = ['docx_normal', 'docx_censored', 'zip_normal', 'zip_censored']

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
    const censoredCitizen = wrapper.find('#censoredCitizen')
    const censoredInstitution = wrapper.find('#censoredInstitution')
    const obscured = wrapper.find('#obscured')

    expect(censoredCitizen.exists()).toBe(true)
    expect(censoredInstitution.exists()).toBe(true)
    expect(obscured.exists()).toBe(true)
  })

  it('does not render checkboxes for isCensored and isObscure when export type is xlsx', async () => {
    await wrapper.setData({ active: 'xlsx_normal' })
    const censoredCitizen = wrapper.find('#censoredCitizen')
    const censoredInstitution = wrapper.find('#censoredInstitution')
    const obscured = wrapper.find('#obscured')

    expect(censoredCitizen.exists()).toBe(false)
    expect(censoredInstitution.exists()).toBe(false)
    expect(obscured.exists()).toBe(false)
  })

  it('emits export event with initial column titles when no changes are made', () => {
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0] /** It returns an array with all the occurrences of `this.$emit('export')` */

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: null,
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: false,
      isObscured: false
    })
  })

  it('emits export event with updated col2 title', () => {
    wrapper.setData({
      docxColumns: {
        col1: { title: sessionStorageValue },
        col2: { title: 'Test Column Title' },
        col3: { title: null }
      }
    })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: false,
      isObscured: false
    })
  })

  it('emits export event with null docxHeaders for xlsx export type', () => {
    wrapper.setData({ active: 'xlsx_normal' })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_xls_export',
      docxHeaders: null,
      fileNameTemplate: null,
      shouldConfirm: false,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: false,
      isObscured: false
    })
  })

  it('emits export event with isCitizenDataCensored true if censoredCitizen is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isCitizenDataCensored: true,
      isInstitutionDataCensored: false,
      exportTypes: {
        docx_censored: { exportPath: 'dplan_statement_segments_export' }
      },
      docxColumns: {
        col1: { title: sessionStorageValue },
        col2: { title: 'Test Column Title' },
        col3: { title: null }
      }
    })
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1:sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: true,
      isInstitutionDataCensored: false,
      isObscured: false
    })
  })

  it('emits export event with isCitizenDataCensored true and isInstitutionDataCensored true for if censoredCitizen and censoredInstitution are selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isInstitutionDataCensored: true,
      isCitizenDataCensored: true
    })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: true,
      isInstitutionDataCensored: true,
      isObscured: false
    })
  })

  it('emits export event with isObscured true for docx_obscured export type', () => {
    wrapper.setData({
      active: 'docx_normal',
      isObscure: true,
      docxColumns: {
        col1: { title: sessionStorageValue },
        col2: { title: 'Test Column Title' },
        col3: { title: null }
      }
    })
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
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
