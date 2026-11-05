/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import DpVersionHistory from '@DpJs/components/statement/statement/DpVersionHistory.vue'

const { mockDpApi } = vi.hoisted(() => ({ mockDpApi: vi.fn() }))

vi.mock('@demos-europe/demosplan-ui', async importOriginal => ({
  ...(await importOriginal()),
  dpApi: (...args: unknown[]) => mockDpApi(...args),
}))

describe('DpVersionHistory', () => {
  const mountHistory = (props = {}) => shallowMount(DpVersionHistory, {
    props: {
      procedureId: 'procedure-1',
      ...props,
    },
  })

  beforeEach(() => {
    mockDpApi.mockReset()
    mockDpApi.mockResolvedValue({ data: { data: [], included: [] } })
  })

  it('renders nothing and loads nothing without an entity', () => {
    const wrapper = mountHistory()

    expect(wrapper.html()).toBe('<!--v-if-->')
    expect(mockDpApi).not.toHaveBeenCalled()
  })

  it.each([
    ['statement', 'dplan_api_statement_history_get', { statementId: 'entity-1' }],
    ['segment', 'dplan_api_segment_history_get', { segmentId: 'entity-1' }],
    ['fragment', 'dplan_api_statement_fragment_history', { statementFragmentId: 'entity-1', procedureId: 'procedure-1' }],
  ])('loads the %s history on mount', async (entityType, route, params) => {
    mountHistory({ entityId: 'entity-1', entityType })
    await flushPromises()

    expect(Routing.generate).toHaveBeenLastCalledWith(route, params)
    expect(mockDpApi).toHaveBeenCalledTimes(1)
  })

  it('reloads when the entity changes', async () => {
    const wrapper = mountHistory({ entityId: 'entity-1', entityType: 'segment' })

    await flushPromises()

    await wrapper.setProps({ entityId: 'entity-2' })
    await flushPromises()

    expect(mockDpApi).toHaveBeenCalledTimes(2)
    expect(Routing.generate).toHaveBeenLastCalledWith('dplan_api_segment_history_get', { segmentId: 'entity-2' })
  })

  it('reloads the same entity when the reload key is bumped', async () => {
    const wrapper = mountHistory({ entityId: 'entity-1', entityType: 'statement', reloadKey: 1 })

    await flushPromises()

    await wrapper.setProps({ reloadKey: 2 })
    await flushPromises()

    expect(mockDpApi).toHaveBeenCalledTimes(2)
  })

  it('loads once when entity and reload key change together', async () => {
    const wrapper = mountHistory()

    await wrapper.setProps({ entityId: 'entity-1', entityType: 'statement', reloadKey: 1 })
    await flushPromises()

    expect(mockDpApi).toHaveBeenCalledTimes(1)
  })

  it('shows the entity in the heading', async () => {
    const wrapper = mountHistory({ entityId: 'entity-1', entityType: 'segment', externId: 'S1' })

    await flushPromises()

    expect(wrapper.find('h2').text()).toBe('segment S1 - history')
  })
})
