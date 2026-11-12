/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import SegmentsList from '@DpJs/components/procedure/SegmentsList/SegmentsList.vue'

describe('SegmentsList.getCustomFieldOptionLabel', () => {
  const options = [
    { id: 'option-1', label: 'Noise' },
    { id: 'option-2', label: 'Traffic' },
    { id: 'option-3', label: 'Housing' },
  ]

  const customFieldDefinitions = [
    { id: 'single-field', attributes: { fieldType: 'singleSelect', options } },
    { id: 'multi-field', attributes: { fieldType: 'multiSelect', options } },
  ]

  const getLabel = (customFields, fieldId) =>
    SegmentsList.methods.getCustomFieldOptionLabel.call({ customFieldDefinitions }, customFields, fieldId)

  it('returns the label of a singleSelect value', () => {
    expect(getLabel([{ id: 'single-field', value: 'option-2' }], 'single-field')).toBe('Traffic')
  })

  it('joins the labels of a multiSelect value', () => {
    expect(getLabel([{ id: 'multi-field', value: ['option-1', 'option-3'] }], 'multi-field')).toBe('Noise, Housing')
  })

  it('returns an empty string without a value', () => {
    expect(getLabel([], 'multi-field')).toBe('')
    expect(getLabel(null, 'multi-field')).toBe('')
    expect(getLabel([{ id: 'multi-field', value: [] }], 'multi-field')).toBe('')
    expect(getLabel([{ id: 'single-field', value: null }], 'single-field')).toBe('')
  })

  it('skips an option that the field no longer has', () => {
    expect(getLabel([{ id: 'multi-field', value: ['deleted-option', 'option-1'] }], 'multi-field')).toBe('Noise')
  })
})
