/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import DpEditFieldMultiSelect from '@DpJs/components/statement/assessmentTable/DpEditFieldMultiSelect'
import Vuex from 'vuex'

const localVue = createLocalVue()

localVue.use(Vuex)

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
    store = new Vuex.Store({
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
    const instance = shallowMount(DpEditFieldMultiSelect, {
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
      localVue,
      store
    })

    expect(instance.vm.assessmentBaseLoaded).toBe(true)
  })
})
