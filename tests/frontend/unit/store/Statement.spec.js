/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createApp } from 'vue'
import { createStore } from 'vuex'
import Statement from '.@DpJs/store/statement/Statement'

const localVue = createApp({})
let StubStore
global.Vue = localVue

// Non-instance tests
describe('Statement', () => {
  it('is namespaced', () => {
    expect(Object.hasOwn(Statement, 'namespaced')).toBe(true)
    expect(Statement.namespaced).toBe(true)
  })

  it('has a statements list', () => {
    expect(Object.hasOwn(Statement, 'state')).toBe(true)
    expect(Object.hasOwn(Statement.state, 'statements')).toBe(true)
    expect(Statement.state.statements instanceof Object).toBe(true)
  })
})

describe('StatementStore', () => {
  beforeEach(() => {
    StubStore = createStore({})
    StubStore.registerModule('Statement', Statement)
  })

  it('can add a statement', () => {
    expect(Object.keys(StubStore.state.Statement.statements)).toHaveLength(0)

    const statement = { id: '123-456-234' }
    StubStore.commit('Statement/addStatement', statement)
    expect(Object.keys(StubStore.state.Statement.statements)).toHaveLength(1)
  })
})
