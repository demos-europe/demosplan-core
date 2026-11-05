/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it } from 'vitest'
import { createStore } from 'vuex'
import VersionHistorySlidebarStore from '@DpJs/store/statement/VersionHistorySlidebar'

describe('VersionHistorySlidebar store', () => {
  let store

  beforeEach(() => {
    store = createStore({ modules: { VersionHistorySlidebar: VersionHistorySlidebarStore } })
  })

  const state = () => store.state.VersionHistorySlidebar

  it('opens the slidebar for the requested entity', () => {
    const entity = { externId: 'M1', id: 'statement-1', type: 'statement' }

    store.commit('VersionHistorySlidebar/showHistory', entity)

    expect(state().entity).toEqual(entity)
    expect(state().isOpen).toBe(true)
  })

  it('bumps the reload key when the same entity is requested again', () => {
    const entity = { externId: 'M1', id: 'statement-1', type: 'statement' }

    store.commit('VersionHistorySlidebar/showHistory', entity)
    const reloadKey = state().reloadKey

    store.commit('VersionHistorySlidebar/showHistory', entity)

    expect(state().reloadKey).toBe(reloadKey + 1)
  })

  it('bumps the reload key only when the shown entity was updated', () => {
    store.commit('VersionHistorySlidebar/showHistory', { externId: 'M1', id: 'statement-1', type: 'statement' })
    const reloadKey = state().reloadKey

    store.commit('VersionHistorySlidebar/markEntityUpdated', 'statement-2')
    expect(state().reloadKey).toBe(reloadKey)

    store.commit('VersionHistorySlidebar/markEntityUpdated', 'statement-1')
    expect(state().reloadKey).toBe(reloadKey + 1)
  })

  it('ignores updates while no entity is shown', () => {
    store.commit('VersionHistorySlidebar/markEntityUpdated', 'statement-1')

    expect(state().reloadKey).toBe(0)
  })
})
