import { describe, beforeEach, afterEach, beforeAll, it, expect } from '@jest/globals'
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
    button.trigger('click')
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
    expect(wrapper.vm.$data.active).toBe('docx')
    expect(wrapper.vm.docxColumns.col1.title).toBe(sessionStorageValue)
    expect(wrapper.vm.docxColumns.col2.title).toBe(null)
    expect(wrapper.vm.docxColumns.col3.title).toBe(null)
  })

  it('renders input fields when export type is docx or zip', async () => {
    const exportTypes = ['docx', 'zip']

    exportTypes.map(async exportType => {
      await wrapper.setData({ active: exportType })
      const inputs = wrapper.findAllComponents({ name: 'DpInput' })

      expect(inputs.length).toBe(Object.keys(wrapper.vm.docxColumns).length)
    })
  })

  it('does not render input fields when export type is not docx or zip', async () => {
    await wrapper.setData({ active: 'xlsx' })
    const inputs = wrapper.findAllComponents({ name: 'DpInput' })

    expect(inputs.length).toBe(0)
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
      }
    })
  })

  it('emits export event with updated col2 title', () => {
    const spy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.setData({
      ...wrapper.vm.docxColumns,
      docxColumns: {
        col2: { title: 'Test Column Title' }
      }
    })
    wrapper.vm.handleExport()

    expect(spy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: sessionStorageValue,
        col2: 'Test Column Title',
        col3: null
      }
    })
  })

  it('emits export event with null docxHeaders for xlsx export type',  () => {
    const emitSpy = jest.spyOn(wrapper.vm, '$emit')
    wrapper.setData({ active: 'xlsx' })
    wrapper.vm.handleExport()

    expect(emitSpy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_xls_export',
      docxHeaders: null
    })
  })

  it('closes the DpModal after executing the handleExport function', () =>  {
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
