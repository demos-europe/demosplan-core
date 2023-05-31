/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import DpFilterModal from '@DpJs/components/statement/assessmentTable/DpFilterModal'
import Vuex from 'vuex'

const localVue = createLocalVue()

localVue.use(Vuex)
window.dplan = () => { return {} }

describe('FilterModal', () => {
  it('should be an object', () => {
    expect(typeof DpFilterModal).toBe('object')
  })

  it('should be named dp-filter-modal', () => {
    expect(DpFilterModal.name).toBe('DpFilterModal')
  })

  let store
  let actions
  let getters

  beforeEach(() => {
    actions = {
      getFilterAction: jest.fn()
    }

    getters = {
      filterList: jest.fn(),
      filterGroups: jest.fn(),
      filterByType: jest.fn(),
      procedureId: jest.fn()
    }

    store = new Vuex.Store({
      modules: {
        filter: {
          state: {
            filterGroups: [],
            filterList: [],
            procedureId: ''
          },
          getters: getters,
          actions: actions
        }
      }
    })
  })
})
