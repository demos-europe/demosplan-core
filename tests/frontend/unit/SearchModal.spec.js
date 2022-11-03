/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import SearchModal from '@DpJs/components/statement/assessmentTable/SearchModal/SearchModal'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

import Vuex from 'vuex'

describe('SearchModal', () => {
  let store
  let mutations

  beforeEach(() => {
    mutations = {
      setCurrentSearch: jest.fn()
    }

    store = new Vuex.Store({
      modules: {
        filter: {
          namespaced: true,
          state: {},
          mutations
        }
      }
    })
  })

  it('should be an object', () => {
    expect(typeof SearchModal).toBe('object')
  })

  it('should be named search-modal', () => {
    expect(SearchModal.name).toBe('SearchModal')
  })

  it('renders the correct markup with deactivated feature_statements_tag and feature_statement_fragments_tag', () => {
    global.dplan.permissions = {
      feature_statements_tag: false,
      feature_statement_fragments_tag: false
    }

    const wrapper = shallowMountWithGlobalMocks(
      SearchModal,
      { store }
    )

    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders the correct markup for activated field_statement_municipality', () => {
    global.dplan.permissions = {
      field_statement_municipality: true
    }

    const wrapper = shallowMountWithGlobalMocks(
      SearchModal,
      { store }
    )

    expect(wrapper.html()).toMatchSnapshot()
  })
})
