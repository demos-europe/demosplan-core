/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

const { mockDpApiGet } = vi.hoisted(() => ({ mockDpApiGet: vi.fn() }))

vi.mock('@demos-europe/demosplan-ui', async importOriginal => ({
  ...(await importOriginal<Record<string, unknown>>()),
  dpApi: { get: (...args: unknown[]) => mockDpApiGet(...args) },
}))

const respondWith = (customFields: unknown[]) => ({ data: { data: { attributes: { customFields } } } })

describe('useCustomFields clearCustomFieldValues', () => {
  beforeEach(() => {
    mockDpApiGet.mockReset()
  })

  it('makes the next fetch load the values from the server again', async () => {
    const { clearCustomFieldValues, fetchCustomFieldValues } = useCustomFields()

    mockDpApiGet
      .mockResolvedValueOnce(respondWith([{ id: 'field-1', value: 'saved' }]))
      .mockResolvedValueOnce(respondWith([]))

    expect(await fetchCustomFieldValues('DraftStatement', 'draft-clear')).toEqual([{ id: 'field-1', value: 'saved' }])
    // Served from the cache without a request
    expect(await fetchCustomFieldValues('DraftStatement', 'draft-clear')).toEqual([{ id: 'field-1', value: 'saved' }])
    expect(mockDpApiGet).toHaveBeenCalledTimes(1)

    clearCustomFieldValues('DraftStatement', 'draft-clear')

    expect(await fetchCustomFieldValues('DraftStatement', 'draft-clear')).toEqual([])
    expect(mockDpApiGet).toHaveBeenCalledTimes(2)
  })

  it('keeps the cached values of other resources', async () => {
    const { clearCustomFieldValues, fetchCustomFieldValues, hasCachedValues } = useCustomFields()

    mockDpApiGet.mockResolvedValue(respondWith([]))
    await fetchCustomFieldValues('DraftStatement', 'draft-keep')
    await fetchCustomFieldValues('DraftStatement', 'draft-drop')

    clearCustomFieldValues('DraftStatement', 'draft-drop')

    expect(hasCachedValues('DraftStatement', 'draft-keep')).toBe(true)
    expect(hasCachedValues('DraftStatement', 'draft-drop')).toBe(false)
  })
})
