/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import DpEditFieldMultiSelect from '@DpJs/components/statement/assessmentTable/DpEditFieldMultiSelect.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import { vi } from 'vitest'
import { VueWrapper } from '@vue/test-utils'

describe('DpEditFieldMultiSelect', () => {
  const AssessmentTable = {
    state: {

    },
    actions: {

    },
    getters: {
      assessmentBaseLoaded: vi.fn(),
    },

  }

  let store: ReturnType<typeof createStore>

  beforeEach(() => {
    store = createStore({
      modules: {
        AssessmentTable: {
          state: AssessmentTable.state,
          getters: AssessmentTable.getters,
          actions: AssessmentTable.actions,
        },
      },
    })
  })

  it('should load assessmentBase', () => {
    const instance = shallowMountWithGlobalMocks(DpEditFieldMultiSelect, {
      props: {
        entityId: 'entId',
        fieldKey: 'aaa',
        options: [],
        label: 'label',
      },
      computed: {
        assessmentBaseLoaded: () => true,
      },
      stubs: {
        'dp-multiselect': true,
      },
      global: {
        plugins: [store],
      },
    }) as VueWrapper<any>

    expect(instance.vm.assessmentBaseLoaded).toBe(true)
  })

  it('reset() clears the child DpEditField and emits toggleEditing false', () => {
    const finishEditingSpy = vi.fn()
    const options = [
      { id: '1', name: 'one' },
      { id: '2', name: 'two' },
    ]

    const instance = shallowMountWithGlobalMocks(DpEditFieldMultiSelect, {
      props: {
        entityId: 'entId',
        fieldKey: 'aaa',
        options,
        label: 'label',
        value: [options[0]],
      },
      global: {
        plugins: [store],
        stubs: {
          DpEditField: {
            name: 'DpEditField',
            template: '<div />',
            methods: {
              finishEditing: finishEditingSpy,
            },
          },
          'dp-multiselect': true,
        },
      },
    }) as VueWrapper<any>

    instance.vm.selected = [options[1]]
    instance.vm.reset()

    expect(finishEditingSpy).toHaveBeenCalledOnce()
    expect(instance.emitted('toggleEditing')?.[0]).toEqual([false])
    expect(instance.vm.selected).toEqual([options[0]])
  })
})
