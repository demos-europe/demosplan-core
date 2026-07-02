import { afterEach, beforeEach, describe, expect, it } from '@jest/globals'
import { DpModal } from '@demos-europe/demosplan-ui'
import { enableAutoUnmount } from '@vue/test-utils'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import StatementExportModal from '@DpJs/components/statement/StatementExportModal'

describe('StatementExportModal', () => {
  const MOCK_PROCEDURE_ID = 'procedure-123'
  let wrapper

  const findCheckboxes = () => {
    return {
      censoredCitizen: wrapper.find('#censoredCitizen'),
      censoredInstitution: wrapper.find('#censoredInstitution'),
      obscured: wrapper.find('#obscured'),
    }
  }

  const defaultDocxHeaders = {
    col1: null,
    col2: null,
    col3: null,
  }

  const defaultPayload = {
    customHeaderText: null,
    docxHeaders: defaultDocxHeaders,
    fileNameTemplate: null,
    isCitizenDataCensored: false,
    isInstitutionDataCensored: false,
    isObscured: false,
    tagFilterIds: [],
    uploadedDocxTemplate: null,
  }

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(StatementExportModal, {
      props: {
        isSingleStatementExport: false,
        procedureId: MOCK_PROCEDURE_ID,
      },
      global: {
        renderStubDefaultSlot: true,
        stubs: {
          'dp-modal': {
            template: '<div><slot /></div>',
            methods: {
              toggle: jest.fn(),
            },
          },
          'filter-flyout': {
            template: '<div></div>',
            methods: {
              reset: jest.fn(),
            },
          },
        },
      },
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
      active: 'docx_normal',
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
      shouldConfirm: true,
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with updated col2 title', () => {
    const docxColumns = {
      col1: { title: null },
      col2: { title: 'Test Column Title' },
      col3: { title: null },
    }
    const docxHeaders = Object.fromEntries(Object.entries(docxColumns).map(([key, value]) => [key, value.title]))

    wrapper.setData({
      docxColumns,
    })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]
    const payload = {
      ...defaultPayload,
      route: 'dplan_statement_segments_export',
      docxHeaders,
      shouldConfirm: true,
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with customHeaderText when set', () => {
    const customHeaderText = 'Custom header text'

    wrapper.setData({ customHeaderText })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]
    const payload = {
      ...defaultPayload,
      customHeaderText,
      route: 'dplan_statement_segments_export',
      shouldConfirm: true,
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
      shouldConfirm: false,
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with isCitizenDataCensored true if censoredCitizen is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isCitizenDataCensored: true,
    })
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]
    const payload = {
      ...defaultPayload,
      isCitizenDataCensored: true,
      route: 'dplan_statement_segments_export',
      shouldConfirm: true,
    }

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual(payload)
  })

  it('emits export event with isInstitutionDataCensored true if censoredInstitution is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isCitizenDataCensored: false,
      isInstitutionDataCensored: true,
    })
    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      customHeaderText: null,
      docxHeaders: defaultDocxHeaders,
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: true,
      isObscured: false,
      tagFilterIds: [],
      uploadedDocxTemplate: null,
    })
  })

  it('emits export event with isObscured true if obscured checkbox is selected', () => {
    wrapper.setData({
      active: 'docx_normal',
      isObscure: true,
    })
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      route: 'dplan_statement_segments_export',
      customHeaderText: null,
      docxHeaders: defaultDocxHeaders,
      fileNameTemplate: null,
      shouldConfirm: true,
      isCitizenDataCensored: false,
      isInstitutionDataCensored: false,
      isObscured: true,
      tagFilterIds: [],
      uploadedDocxTemplate: null,
    })
  })

  it('calls updateSelectedTags when getFilterValues is called', () => {
    const updateSelectedTagIdsSpy = jest.spyOn(wrapper.vm, 'updateSelectedTagIds')
    const updateSelectedTagsSpy = jest.spyOn(wrapper.vm, 'updateSelectedTags')

    wrapper.vm.getFilterValues({})

    expect(updateSelectedTagIdsSpy).toHaveBeenCalledTimes(1)
    expect(updateSelectedTagsSpy).toHaveBeenCalledTimes(1)
  })

  it('syncs selectedTags from filterFlyout when tag filters are applied', () => {
    const MOCK_TAG_ID_1 = 'tagID1'
    const MOCK_TAG_ID_2 = 'tagID2'
    const itemsSelectedMock = [
      { id: MOCK_TAG_ID_1, label: 'Tag 1' },
      { id: MOCK_TAG_ID_2, label: 'Tag 2' },
    ]

    const flyoutRef = wrapper.vm.$refs.filterFlyout

    expect(flyoutRef).toBeTruthy()
    flyoutRef.itemsSelected = itemsSelectedMock

    const filter = {
      MOCK_TAG_ID_1: {
        condition: {
          operator: 'ARRAY_CONTAINS_VALUE',
          path: 'tags',
          value: MOCK_TAG_ID_1,
        },
      },
      MOCK_TAG_ID_2: {
        condition: {
          operator: 'ARRAY_CONTAINS_VALUE',
          path: 'tags',
          value: MOCK_TAG_ID_2,
        },
      },
    }

    wrapper.vm.getFilterValues(filter)
    expect(wrapper.vm.selectedTags).toEqual(itemsSelectedMock)
  })

  it('clears selectedTags when filter is empty and selectedTagIds are not presented', () => {
    const MOCK_TAG_ID_1 = 'tagID1'
    const MOCK_TAG_ID_2 = 'tagID2'
    const itemsSelectedMock = [
      { id: MOCK_TAG_ID_1, label: 'Tag 1' },
      { id: MOCK_TAG_ID_2, label: 'Tag 2' },
    ]

    const flyoutRef = wrapper.vm.$refs.filterFlyout

    expect(flyoutRef).toBeTruthy()
    flyoutRef.itemsSelected = itemsSelectedMock

    const filter = {}

    wrapper.vm.getFilterValues(filter)
    expect(wrapper.vm.selectedTags).toEqual([])
  })

  it('emits export event with "tagFilterIds" from selected filters', () => {
    const MOCK_TAG_ID_1 = 'tagID1'
    const MOCK_TAG_ID_2 = 'tagID2'

    const filter = {
      MOCK_TAG_ID_1: {
        condition: {
          operator: 'ARRAY_CONTAINS_VALUE',
          path: 'tags',
          value: MOCK_TAG_ID_1,
        },
      },
      MOCK_TAG_ID_2: {
        condition: {
          operator: 'ARRAY_CONTAINS_VALUE',
          path: 'tags',
          value: MOCK_TAG_ID_2,
        },
      },
    }

    wrapper.vm.getFilterValues(filter)
    wrapper.vm.handleExport()

    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent).toBeTruthy()
    expect(exportEvent).toEqual({
      ...defaultPayload,
      route: 'dplan_statement_segments_export',
      shouldConfirm: true,
      tagFilterIds: [MOCK_TAG_ID_1, MOCK_TAG_ID_2],
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

  it('renders customHeaderText input only when docx_normal, not single export, and permission granted', async () => {
    const selector = '[datacy="exportModal:customHeaderText"]'

    expect(wrapper.find(selector).exists()).toBe(false)

    await wrapper.setProps({ hasPermissionAdjustPreamble: true })
    expect(wrapper.find(selector).exists()).toBe(true)

    await wrapper.setData({ active: 'xlsx_normal' })
    expect(wrapper.find(selector).exists()).toBe(false)

    await wrapper.setData({ active: 'docx_normal' })
    await wrapper.setProps({ isSingleStatementExport: true })
    expect(wrapper.find(selector).exists()).toBe(false)
  })

  it('requests the full-export placeholder when no tag filters are selected', () => {
    const transSpy = jest.spyOn(globalThis.Translator, 'trans')
    transSpy.mockClear()

    expect(wrapper.vm.customHeaderPlaceholder).toBe('docx.export.header.custom.placeholder')
    expect(transSpy).toHaveBeenCalledWith(
      'docx.export.header.custom.placeholder',
      expect.objectContaining({ isPartialExport: false }),
    )
  })

  it('requests the partial-export placeholder when tag filters are selected', async () => {
    const transSpy = jest.spyOn(globalThis.Translator, 'trans')
    await wrapper.setData({ selectedTagIds: ['tagID1'] })
    transSpy.mockClear()

    expect(wrapper.vm.customHeaderPlaceholder).toBe('docx.export.header.custom.placeholder')
    expect(transSpy).toHaveBeenCalledWith(
      'docx.export.header.custom.placeholder',
      expect.objectContaining({ isPartialExport: true }),
    )
  })

  it('emits the via-template route and hash when single statement export has an uploaded template and permission', async () => {
    await wrapper.setProps({ isSingleStatementExport: true })
    await wrapper.setData({ uploadedHash: 'template-hash-123' })

    wrapper.vm.handleExport()
    const exportEvent = wrapper.emitted('export')[0][0]

    expect(exportEvent.route).toBe('dplan_statement_via_template_export')
    expect(exportEvent.uploadedDocxTemplate).toBe('template-hash-123')
  })

  it('falls back to the default single-statement route when the template permission is missing', async () => {
    const originalHasPermission = globalThis.hasPermission
    globalThis.hasPermission = jest.fn(() => false)

    try {
      await wrapper.setProps({ isSingleStatementExport: true })
      await wrapper.setData({ uploadedHash: 'template-hash-123' })

      wrapper.vm.handleExport()
      const exportEvent = wrapper.emitted('export')[0][0]

      expect(exportEvent.route).toBe('dplan_segments_export')
      expect(exportEvent.uploadedDocxTemplate).toBe(null)
    } finally {
      globalThis.hasPermission = originalHasPermission
    }
  })

  it('restores uploadedHash from sessionStorage in setInitialValues', () => {
    globalThis.sessionStorage.setItem(`templateHash_${MOCK_PROCEDURE_ID}`, JSON.stringify([{ hash: 'stored-hash' }]))
    wrapper.vm.setInitialValues()

    expect(wrapper.vm.uploadedHash).toBe('stored-hash')
  })

  it('clears uploadedHash in setInitialValues when no template is stored', () => {
    wrapper.vm.setInitialValues()

    expect(wrapper.vm.uploadedHash).toBe('')
  })

  it('resets uploadedHash when resetExportModalState is called', async () => {
    await wrapper.setData({ uploadedHash: 'some-hash' })
    wrapper.vm.resetExportModalState()

    expect(wrapper.vm.uploadedHash).toBe('')
  })

  it('displays column header warning only if single statement export, hash uploaded, permission granted, and column title is set', async () => {
    await wrapper.setProps({ isSingleStatementExport: true })
    await wrapper.setData({
      uploadedHash: 'template-hash',
      docxColumns: { col1: { title: 'My Title' } },
    })

    expect(wrapper.findComponent({ name: 'DpInlineNotification' }).exists()).toBe(true)
  })
})
