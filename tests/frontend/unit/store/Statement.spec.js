/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import Statement from '.@DpJs/store/statement/Statement'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)
let StubStore
global.Vue = localVue

// Non-instance tests
describe('Statement', () => {
  it('is namespaced', () => {
    expect(Statement.hasOwnProperty('namespaced')).toBe(true)
    expect(Statement.namespaced).toBe(true)
  })

  it('has a statements list', () => {
    expect(Statement.hasOwnProperty('state')).toBe(true)
    expect(Statement.state.hasOwnProperty('statements')).toBe(true)
    expect(Statement.state.statements instanceof Object).toBe(true)
  })
})

describe('StatementStore', () => {
  beforeEach(() => {
    StubStore = new Vuex.Store({})
    StubStore.registerModule('statement', Statement)
  })

  it('can add a statement', () => {
    expect(Object.keys(StubStore.state.statement.statements)).toHaveLength(0)

    const statement = { id: '123-456-234' }
    StubStore.commit('statement/addStatement', statement)
    expect(Object.keys(StubStore.state.statement.statements)).toHaveLength(1)
  })
})
