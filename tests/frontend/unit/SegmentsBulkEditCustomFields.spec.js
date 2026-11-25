/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import SegmentsBulkEdit from '@DpJs/components/procedure/SegmentsBulkEdit/SegmentsBulkEdit.vue'

describe('SegmentsBulkEdit custom fields', () => {
  const options = [
    { id: 'option-1', label: 'Noise' },
    { id: 'option-2', label: 'Traffic' },
  ]

  const createDefinition = (id, fieldType) => ({
    id,
    attributes: { fieldType, name: `Field ${id}`, options },
  })

  beforeEach(() => {
    globalThis.hasPermission = vi.fn(() => true)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('addCustomFieldsToActions', () => {
    it('starts a multiSelect with an empty list and a singleSelect with null', () => {
      const context = {
        actions: { customFields: [] },
        customFieldDefinitions: [
          createDefinition('multi-field', 'multiSelect'),
          createDefinition('single-field', 'singleSelect'),
        ],
      }

      SegmentsBulkEdit.methods.addCustomFieldsToActions.call(context)

      const [multi, single] = context.actions.customFields

      expect(multi).toMatchObject({ id: 'multi-field', isMultiple: true, selected: [] })
      expect(single).toMatchObject({ id: 'single-field', isMultiple: false, selected: null })
    })
  })

  describe('customFieldsCheckedAndSelected', () => {
    const getSelectedFields = (customFields) =>
      SegmentsBulkEdit.computed.customFieldsCheckedAndSelected.call({ actions: { customFields } })
        .map(customField => customField.id)

    it('ignores a checked multiSelect without options and keeps one with options', () => {
      expect(getSelectedFields([{ checked: true, id: 'empty', selected: [] }])).toEqual([])
      expect(getSelectedFields([{ checked: true, id: 'filled', selected: ['Noise'] }])).toEqual(['filled'])
    })

    it('ignores a checked singleSelect without a label and keeps one with a label', () => {
      expect(getSelectedFields([{ checked: true, id: 'empty', selected: null }])).toEqual([])
      expect(getSelectedFields([{ checked: true, id: 'filled', selected: 'Noise' }])).toEqual(['filled'])
    })

    it('ignores fields that are not checked', () => {
      expect(getSelectedFields([{ checked: false, id: 'unchecked', selected: ['Noise'] }])).toEqual([])
    })
  })

  describe('selectedTags', () => {
    it('returns one tag per label of a multiSelect', () => {
      const tags = SegmentsBulkEdit.methods.selectedTags.call({}, { id: 'field-1', selected: ['Noise', 'Traffic'] })

      expect(tags.map(tag => tag.title)).toEqual(['Noise', 'Traffic'])
      expect(new Set(tags.map(tag => tag.id)).size).toBe(2)
    })

    it('returns one tag for the label of a singleSelect', () => {
      const tags = SegmentsBulkEdit.methods.selectedTags.call({}, { id: 'field-1', selected: 'Noise' })

      expect(tags.map(tag => tag.title)).toEqual(['Noise'])
    })
  })
})
