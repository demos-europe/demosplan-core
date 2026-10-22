/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import DpEditFieldSingleSelect from '@DpJs/components/statement/assessmentTable/DpEditFieldSingleSelect.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import { vi } from 'vitest'
import { VueWrapper } from '@vue/test-utils'

describe('DpEditFieldSingleSelect', () => {
  let store: ReturnType<typeof createStore>

  beforeEach(() => {
    store = createStore({
      modules: {
        AssessmentTable: {
          state: {},
          getters: {
            assessmentBaseLoaded: vi.fn(),
          },
          actions: {},
        },
      },
    })
  })

  it('reset() clears the child DpEditField and emits toggleEditing false', () => {
    const finishEditingSpy = vi.fn()
    const options = [
      { id: '1', title: 'one' },
      { id: '2', title: 'two' },
    ]

    const instance = shallowMountWithGlobalMocks(DpEditFieldSingleSelect, {
      props: {
        entityId: 'entId',
        fieldKey: 'aaa',
        options,
        label: 'label',
        value: options[0],
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

    instance.vm.selected = options[1]
    instance.vm.reset()

    expect(finishEditingSpy).toHaveBeenCalledOnce()
    expect(instance.emitted('toggleEditing')?.[0]).toEqual([false])
    expect(instance.vm.selected).toEqual(options[0])
  })
})
