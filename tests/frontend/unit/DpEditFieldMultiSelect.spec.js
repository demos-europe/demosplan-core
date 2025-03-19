/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import DpEditFieldMultiSelect from '@DpJs/components/statement/assessmentTable/DpEditFieldMultiSelect'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

window.dplan = () => { return {} }

describe('DpEditFieldMultiSelect', () => {
  const AssessmentTable = {
    state: {

    },
    actions: {

    },
    getters: {
      assessmentBaseLoaded: jest.fn()
    }

  }

  let store

  beforeEach(() => {
    store = createStore({
      modules: {
        AssessmentTable: {
          state: AssessmentTable.state,
          getters: AssessmentTable.getters,
          actions: AssessmentTable.actions
        }
      }
    })
  })

  it('should load assessmentBase', () => {
    const instance = shallowMountWithGlobalMocks(DpEditFieldMultiSelect, {
      propsData: {
        entityId: 'entId',
        fieldKey: 'aaa',
        options: [],
        label: 'label'
      },
      computed: {
        assessmentBaseLoaded: () => true
      },
      stubs: {
        'dp-multiselect': true
      },
      global: {
        plugins: [store]
      }
    })

    expect(instance.vm.assessmentBaseLoaded).toBe(true)
  })
})
