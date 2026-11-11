/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import CreateCustomFieldForm from '@DpJs/components/procedure/admin/CreateCustomFieldForm.vue'
import { useCustomFieldTypes } from '@DpJs/composables/useCustomFieldTypes'

describe('useCustomFieldTypes.isFieldTypeSelectableForTarget', () => {
  const { isFieldTypeSelectableForTarget } = useCustomFieldTypes()

  it('allows choosing the type for segments', () => {
    expect(isFieldTypeSelectableForTarget('SEGMENT')).toBe(true)
  })

  it.each(['STATEMENT', 'ORGA', 'UNKNOWN'])('keeps the type fixed for %s', (targetEntity) => {
    expect(isFieldTypeSelectableForTarget(targetEntity)).toBe(false)
  })
})

describe('CreateCustomFieldForm.isTypeSelectDisabled', () => {
  const isDisabled = (targetEntity) =>
    CreateCustomFieldForm.computed.isTypeSelectDisabled.call({ customField: { targetEntity } })

  it('is enabled as long as no target is chosen', () => {
    expect(isDisabled('')).toBe(false)
  })

  it('is enabled for segments', () => {
    expect(isDisabled('SEGMENT')).toBe(false)
  })

  it.each(['STATEMENT', 'ORGA'])('is disabled for %s', (targetEntity) => {
    expect(isDisabled(targetEntity)).toBe(true)
  })
})
