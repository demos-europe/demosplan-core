/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import Filter from '@DemosPlanStatementBundle/store/Filter'
import { filterList } from './Filter.mock'
import Vuex from 'vuex'

describe('FilterStore', () => {
  let store

  beforeEach(() => {
    const localVue = createLocalVue()
    localVue.use(Vuex)
    store = new Vuex.Store(Filter)
  })

  it('has filterGroups', () => {
    expect(store.state.filterGroups.findIndex(el => el.type === 'submission' && el.label === 'submission')).toBeGreaterThan(-1)
    expect(store.state.filterGroups.findIndex(el => el.type === 'statement' && el.label === 'statement')).toBeGreaterThan(-1)
    expect(store.state.filterGroups.findIndex(el => el.type === 'fragment' && el.label === 'fragment')).toBeGreaterThan(-1)
  })

  it('has a getter that returns filterGroups', () => {
    expect(store.getters.filterGroups.findIndex(el => el.type === 'submission' && el.label === 'submission')).toBeGreaterThan(-1)
    expect(store.getters.filterGroups.findIndex(el => el.type === 'statement' && el.label === 'statement')).toBeGreaterThan(-1)
    expect(store.getters.filterGroups.findIndex(el => el.type === 'fragment' && el.label === 'fragment')).toBeGreaterThan(-1)
  })

  it('has filterList', () => {
    store.state.filterList = filterList

    expect(store.state.filterList).toEqual(filterList)
  })

  it('filters filterList by type', () => {
    store.state.filterList = filterList

    expect(store.getters.filterByType('c')).toHaveLength(1)
    expect(store.getters.filterByType('a')).toHaveLength(2)
  })

  it('removes all zero-counts in filterByType from filterList', () => {
    store.state.filterList = filterList

    expect(store.getters.filterByType('b')[0].attributes.options.find(option => option.count === 0)).toBe(undefined)
  })
})
