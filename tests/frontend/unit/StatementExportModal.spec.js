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
      propsData: {
        isSingleStatementExport: false
      },
      stubs: {
        DpModal
      }
    })

    const button = wrapper.find('[data-cy="exportModal:open"]')
    const mockEvent = { preventDefault: jest.fn() }
    button.vm.$emit('click', mockEvent)
    wrapper.vm.setInitialValues()
  })

  afterEach(() => {
    wrapper.destroy()
  })

  it('opens the modal when the button is clicked', async () => {
    const modal = wrapper.findComponent({ name: 'DpModal' })
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

  it('renders checkboxes for isCensored and isObscure when export type is not xlsx', async () => {
    await wrapper.setData({ active: 'docx_normal' })
    const censoredCheckbox = wrapper.find('#censoredCheckbox')
    const obscureCheckbox = wrapper.find('#obscureCheckbox')

    expect(censoredCheckbox.exists()).toBe(true)
    expect(obscureCheckbox.exists()).toBe(true)
  })

  it('does not render checkboxes for isCensored and isObscure when export type is xlsx', async () => {
    await wrapper.setData({ active: 'xlsx_normal' })
    const censoredCheckbox = wrapper.find('#censoredCheckbox')
    const obscureCheckbox = wrapper.find('#obscureCheckbox')

    expect(censoredCheckbox.exists()).toBe(false)
    expect(obscureCheckbox.exists()).toBe(false)
  })

  it('emits export event with initial column titles when no changes are made', () => {
    const emitSpy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.vm.handleExport()

    expect(emitSpy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: null,
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCensored: false,
      isObscured: false
    })
  })

  it('emits export event with updated col2 title', () => {
    const spy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.setData({
      docxColumns: {
        col1: { title: sessionStorageValue },
        col2: { title: 'Test Column Title' },
        col3: { title: null }
      }
    })
    wrapper.vm.handleExport()

    expect(spy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCensored: false,
      isObscured: false
    })
  })

  it('emits export event with null docxHeaders for xlsx export type', () => {
    const emitSpy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.setData({ active: 'xlsx_normal' })
    wrapper.vm.handleExport()

    expect(emitSpy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_xls_export',
      docxHeaders: null,
      fileNameTemplate: null,
      shouldConfirm: false,
      isCensored: false,
      isObscured: false
    })
  })

  it('emits export event with isCensored true for docx_censored export type', () => {
    const emitSpy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.setData({
      active: 'docx_normal',
      isCensored: true,
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

    expect(emitSpy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCensored: true,
      isObscured: false
    })
  })

  it('emits export event with isObscured true for docx_obscured export type', () => {
    const emitSpy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.setData({
      active: 'docx_normal',
      isObscure: true,
      exportTypes: {
        docx_normal: { exportPath: 'dplan_statement_segments_export' }
      },
      docxColumns: {
        col1: { title: sessionStorageValue },
        col2: { title: 'Test Column Title' },
        col3: { title: null }
      }
    })
    wrapper.vm.handleExport()

    expect(emitSpy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      },
      fileNameTemplate: null,
      shouldConfirm: true,
      isCensored: false,
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
