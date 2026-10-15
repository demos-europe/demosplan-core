/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import DpEditField from '@DpJs/components/statement/assessmentTable/DpEditField.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import { vi } from 'vitest'
import { VueWrapper } from '@vue/test-utils'

describe('DpEditField', () => {
  let store: ReturnType<typeof createStore>

  beforeEach(() => {
    store = createStore({
      modules: {
        AssessmentTable: {
          namespaced: true,
          mutations: {
            setRefreshButtonVisibility: vi.fn(),
          },
        },
      },
    })
  })

  it('finishEditing() resets loading and editingEnabled', () => {
    const instance = shallowMountWithGlobalMocks(DpEditField, {
      props: {
        label: 'label',
      },
      global: {
        plugins: [store],
      },
    }) as VueWrapper<any>

    instance.vm.loading = true
    instance.vm.editingEnabled = true

    instance.vm.finishEditing()

    expect(instance.vm.loading).toBe(false)
    expect(instance.vm.editingEnabled).toBe(false)
  })
})
