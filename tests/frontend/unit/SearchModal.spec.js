/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import SearchModal from '@DpJs/components/statement/assessmentTable/SearchModal/SearchModal'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

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
        Filter: {
          namespaced: true,
          state: {},
          mutations
        }
      }
    })
  })

  it('renders the correct markup with deactivated feature_statements_tag and feature_statement_fragments_tag', () => {
    global.dplan.permissions = {
      feature_statements_tag: false,
      feature_statement_fragments_tag: false
    }

    const wrapper = shallowMountWithGlobalMocks(
      SearchModal,
      {
        store,
        global: {
          renderStubDefaultSlot: true
        }
      }
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
