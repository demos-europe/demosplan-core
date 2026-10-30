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

  const mountModal = (statementActions = {}, props = {}) => {
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
        ...props,
      },
      global: {
        plugins: [store],
        stubs: { DpModal: DpModalStub },
      },
    })
  }

  beforeEach(() => {
    mockDpApi.mockReset()
    dplan.notify.notify.mockClear()
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
   * Pages that don't register the entity module's custom action (e.g. the statement list) pass their own
   * assign function. The modal must not dispatch the unregistered action first.
   */
  it('uses the assignAction prop instead of dispatching a Vuex action', async () => {
    const assignAction = vi.fn(() => Promise.resolve({ id: 'statement-1', assignee: { id: 'user-2', name: 'Selta Seewind' } }))
    const wrapper = mountModal({}, { assignAction })
    const dispatchSpy = vi.spyOn(store, 'dispatch')

    await flushPromises()

    wrapper.vm.selected = { id: 'user-2', name: 'Selta Seewind' }
    wrapper.vm.assignEntity()
    await flushPromises()

    expect(assignAction).toHaveBeenCalledWith({ statementId: 'statement-1', assigneeId: 'user-2' })
    expect(dispatchSpy).not.toHaveBeenCalled()
    expect(wrapper.emitted('assigned')?.[0]?.[0]).toEqual({
      entityId: 'statement-1',
      assignee: { id: 'user-2', name: 'Selta Seewind' },
    })
  })

  it('emits assigned with an empty assignee on unassign', async () => {
    const assignAction = vi.fn(() => Promise.resolve({ id: 'statement-1', assignee: { id: '', name: '' } }))
    const wrapper = mountModal({}, { assignAction })

    await flushPromises()

    wrapper.vm.selected = { id: '', name: '-' }
    wrapper.vm.assignEntity()
    await flushPromises()

    expect(assignAction).toHaveBeenCalledWith({ statementId: 'statement-1', assigneeId: '' })
    expect(wrapper.emitted('assigned')?.[0]?.[0].assignee.id).toBe('')
  })

  // The API response already carries the confirmation message; a second one from the modal would duplicate it.
  it('does not raise its own success notification', async () => {
    const assignAction = vi.fn(() => Promise.resolve({ id: 'statement-1', assignee: { id: 'user-2', name: 'Selta Seewind' } }))
    const wrapper = mountModal({}, { assignAction })

    await flushPromises()

    wrapper.vm.selected = { id: 'user-2', name: 'Selta Seewind' }
    wrapper.vm.assignEntity()
    await flushPromises()

    expect(dplan.notify.notify).not.toHaveBeenCalled()
  })
})
