/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import Notify from '@DpJs/store/core/Notify'
import Vuex from 'vuex'

const LocalVue = createLocalVue()
LocalVue.use(Vuex)
let StubStore

// Non-instance tests
describe('Notify', () => {
  it('is namespaced', () => {
    expect(Notify.hasOwnProperty('namespaced')).toBe(true)
    expect(Notify.namespaced).toBe(true)
  })

  it('has a messages list', () => {
    expect(Notify.hasOwnProperty('state')).toBe(true)
    expect(Notify.state.hasOwnProperty('messages')).toBe(true)
    expect(Notify.state.messages instanceof Array).toBe(true)
  })
})

// Active tests
describe('Notify', () => {
  beforeEach(() => {
    StubStore = new Vuex.Store({})
    StubStore.registerModule('notify', Notify)
  })

  it('can add a message', () => {
    expect(StubStore.state.notify.messages).toHaveLength(0)
    StubStore.commit('notify/add', { text: 'Message Text' })
    expect(StubStore.state.notify.messages).toHaveLength(1)
  })

  it('can remove a message', () => {
    StubStore.commit('notify/remove', { uid: 1 })
    expect(StubStore.state.notify.messages).toHaveLength(0)
  })
})
