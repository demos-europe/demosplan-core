/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import Notify from '@DpJs/store/core/Notify'

let StubStore

describe('Notify - Non-instance tests', () => {
  it('is namespaced', () => {
    expect(Object.hasOwn(Notify, 'namespaced')).toBe(true)
    expect(Notify.namespaced).toBe(true)
  })

  it('has a messages list', () => {
    expect(Object.hasOwn(Notify, 'state')).toBe(true)
    expect(Object.hasOwn(Notify.state, 'messages')).toBe(true)
    expect(Notify.state.messages instanceof Array).toBe(true)
  })
})

describe('Notify - Active tests', () => {
  beforeEach(() => {
    StubStore = createStore({})
    StubStore.registerModule('Notify', Notify)
  })

  it('can add a message', () => {
    expect(StubStore.state.Notify.messages).toHaveLength(0)
    StubStore.commit('Notify/add', { text: 'Message Text' })
    expect(StubStore.state.Notify.messages).toHaveLength(1)
  })

  it('can remove a message', () => {
    StubStore.commit('Notify/remove', { uid: 1 })
    expect(StubStore.state.Notify.messages).toHaveLength(0)
  })
})
