/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AssignEntityModal from '@DpJs/components/statement/assessmentTable/AssignEntityModal'
import { createStore } from 'vuex'

const { mockDpApi } = vi.hoisted(() => ({ mockDpApi: vi.fn() }))

vi.mock('@demos-europe/demosplan-ui', async importOriginal => ({
  ...(await importOriginal()),
  dpApi: (...args) => mockDpApi(...args),
}))

const DpModalStub = {
  template: '<div><slot name="header" /><slot /></div>',
  methods: { toggle: vi.fn() },
}

describe('AssignEntityModal', () => {
  let store

  const mountModal = (statementActions = {}) => {
    store = createStore({
      modules: {
        AssessmentTable: {
          namespaced: true,
          state: {
            modals: {
              assignEntityModal: {
                show: true,
                entityId: 'statement-1',
                entityType: 'statement',
                initialAssigneeId: '',
                parentStatementId: '',
              },
            },
          },
          getters: {
            assignEntityModal: state => state.modals.assignEntityModal,
          },
          mutations: {
            setModalProperty: vi.fn(),
          },
        },
        Statement: {
          namespaced: true,
          actions: statementActions,
        },
      },
    })

    return mount(AssignEntityModal, {
      props: {
        authorisedUsers: [{ id: 'user-2', name: 'Selta Seewind' }],
        currentUserId: 'user-1',
        procedureId: 'procedure-1',
      },
      global: {
        plugins: [store],
        stubs: { DpModal: DpModalStub },
      },
    })
  }

  beforeEach(() => {
    mockDpApi.mockReset()
  })

  it('dispatches the custom Vuex action when the entity module provides one, e.g. on the assessment table', async () => {
    const setAssigneeAction = vi.fn(() => Promise.resolve({ assignee: { id: 'user-2', name: 'Selta Seewind' } }))
    const wrapper = mountModal({ setAssigneeAction })

    // Mounted() reads entityId/entityType from the store via a $nextTick-deferred toggleModal()
    await flushPromises()

    wrapper.vm.selected = { id: 'user-2', name: 'Selta Seewind' }
    wrapper.vm.assignEntity()
    await flushPromises()

    expect(setAssigneeAction).toHaveBeenCalledWith(
      expect.anything(),
      { statementId: 'statement-1', assigneeId: 'user-2' },
    )
    expect(mockDpApi).not.toHaveBeenCalled()
  })

  /*
   * Pages that only register the generic JSON:API 'Statement' module (e.g. the statement list) don't
   * have this custom action. Dispatching to it used to return undefined and crash the promise chain
   * (`Cannot read properties of undefined (reading 'then')`) instead of falling back.
   */
  it('falls back to a direct API call when the entity module has no setAssigneeAction', async () => {
    mockDpApi.mockResolvedValue({
      data: { data: { id: 'user-2', attributes: { name: 'Selta Seewind', orgaName: 'DEMOS Berlin' } } },
    })
    const wrapper = mountModal({})

    // Mounted() reads entityId/entityType from the store via a $nextTick-deferred toggleModal()
    await flushPromises()

    wrapper.vm.selected = { id: 'user-2', name: 'Selta Seewind' }
    wrapper.vm.assignEntity()
    await flushPromises()

    expect(mockDpApi).toHaveBeenCalledWith(expect.objectContaining({ method: 'PATCH' }))
    expect(wrapper.emitted('assigned')?.[0]?.[0]).toEqual({
      entityId: 'statement-1',
      assignee: { id: 'user-2', uId: 'user-2', name: 'Selta Seewind', orgaName: 'DEMOS Berlin' },
    })
  })
})
