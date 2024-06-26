import StatementExportModal from '@DpJs/components/statement/StatementExportModal'
import { describe, beforeEach, afterEach, it, expect } from '@jest/globals'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import { shallowMount, mount } from '@vue/test-utils'
import { DpModal, DpInput } from '@demos-europe/demosplan-ui'
describe('StatementExportModal.vue', () => {
  let wrapper
  let mocks
  let stubs

  beforeEach(() => {
    mocks = {
      Translator: {
        trans: jest.fn(key => key)
      }
    }

    stubs = {
      DpModal,
      DpInput
    }

    wrapper = shallowMount(StatementExportModal, {
      propsData: {
        isSingleStatementExport: false
      },
      stubs,
      mocks,
      data() {
        return {
          active: 'docx'
        }
      }
    })
  })

  afterEach(() => {
    wrapper.destroy()
  })

  it('opens the modal when the button is clicked', async () => {
    const button = wrapper.find('[data-cy="exportModal:open"]')
    await button.trigger('click')
    const modal = wrapper.findComponent({ name: 'DpModal' })
    expect(modal.isVisible()).toBe(true)
  })


  it('renders input fields when export type is docx or zip', async () => {
    const button = wrapper.find('[data-cy="exportModal:open"]')
    await button.trigger('click')
    await wrapper.vm.$nextTick()

    const inputs = wrapper.findAllComponents({ name: 'DpInput' })
    expect(inputs.length).toBe(Object.keys(wrapper.vm.docxColumns).length)
  })

  it('does not render input fields when export type is not docx or zip', async () => {
    wrapper.setData({ active: 'xlsx' })
    await wrapper.vm.$nextTick()

    const button = wrapper.find('[data-cy="exportModal:open"]')
    await button.trigger('click')

    await wrapper.vm.$nextTick()

    const inputs = wrapper.findAllComponents({ name: 'dp-input' })
    expect(inputs.length).toBe(0)
  })

  it('handles export correctly 1', async () => {
    wrapper.setData({
      docxColumns: {
        col1: { title: 'Title1' },
        col2: { title: 'Title2' },
        col3: { title: 'Title3' }
      }
    })
    const spy = jest.spyOn(wrapper.vm, '$emit')
    await wrapper.vm.handleExport()

    expect(spy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_segments_export',
      docxHeaders: {
        col1: 'Title1',
        col2: 'Title2',
        col3: 'Title3'
      }
    })

  })

  it('closes window after the handleExport function', async () =>  {
    const toggleSpy = jest.spyOn(wrapper.vm.$refs.exportModalInner, 'toggle')

    // Call handleExport
    await wrapper.vm.handleExport()

    // Expect toggle method to have been called
    expect(toggleSpy).toHaveBeenCalled()
  })

  it('handles export correctly 2', async () => {
    wrapper.setData({
      active: 'xlsx',
      docxColumns: {
        col1: { title: 'Title1' },
        col2: { title: 'Title2' },
        col3: { title: 'Title3' }
      }
    })
    const spy = jest.spyOn(wrapper.vm, '$emit')
    await wrapper.vm.handleExport()

    expect(spy).toHaveBeenCalledWith('export', {
      route: 'dplan_statement_xls_export',
      docxHeaders: null
    })
  })
})
