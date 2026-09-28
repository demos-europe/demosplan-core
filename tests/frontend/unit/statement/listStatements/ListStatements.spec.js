/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createStore } from 'vuex'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const buildStatement = (relationships = {}) => ({
  id: 'statement-1',
  type: 'Statement',
  attributes: {
    externId: '1000',
    status: 'new',
    internId: '',
    authorName: '',
    submitName: '',
    isSubmittedByCitizen: false,
    initialOrganisationName: '',
    submitDate: '2026-01-01',
    text: '',
    fullText: '',
    isFulltextDisplayed: false,
    textIsTruncated: false,
    segmentsCount: 0,
    synchronized: false,
    isCluster: false,
  },
  relationships: {
    assignee: { data: null },
    ...relationships,
  },
  hasRelationship (name) {
    return Boolean(this.relationships[name]?.data)
  },
})

describe('ListStatements', () => {
  let setModalProperty
  let setItem
  let store

  const mountComponent = () => shallowMountWithGlobalMocks(ListStatements, {
    props: {
      currentUserId: 'user-1',
      procedureId: 'procedure-1',
    },
    global: {
      plugins: [store],
    },
  })

  beforeEach(() => {
    setModalProperty = vi.fn()
    setItem = vi.fn()

    store = createStore({
      modules: {
        AssignableUser: {
          namespaced: true,
          state: { items: {} },
          actions: { list: vi.fn(() => Promise.resolve()) },
        },
        Orga: {
          namespaced: true,
          state: { items: {} },
        },
        Statement: {
          namespaced: true,
          state: {
            items: { 'statement-1': buildStatement() },
            currentPage: 1,
            totalFiles: 0,
            loading: false,
          },
          actions: {
            list: vi.fn(() => Promise.resolve({
              meta: { pagination: { current_page: 1, per_page: 10, count: 1, total: 1, total_pages: 1 } },
            })),
            delete: vi.fn(),
            restoreFromInitial: vi.fn(),
          },
          mutations: {
            setItem,
          },
        },
        AssessmentTable: {
          namespaced: true,
          state: { modals: { assignEntityModal: { show: false } } },
          getters: {
            assignEntityModal: state => state.modals.assignEntityModal,
          },
          mutations: {
            setModalProperty,
          },
        },
      },
    })
  })

  it('opens the assign-entity modal for the given statement and its current assignee', () => {
    const wrapper = mountComponent()

    wrapper.vm.toggleAssignEntityModal('statement-1', 'user-2')

    expect(setModalProperty).toHaveBeenCalledWith(
      expect.anything(),
      {
        prop: 'assignEntityModal',
        val: {
          entityId: 'statement-1',
          entityType: 'statement',
          initialAssigneeId: 'user-2',
          show: true,
        },
      },
    )
  })

  it('updates the row locally once the modal reports a successful assignment, without a reload', () => {
    const wrapper = mountComponent()

    wrapper.vm.handleEntityAssigned({ entityId: 'statement-1', assignee: { id: 'user-2', name: 'Selta Seewind' } })

    expect(setItem).toHaveBeenCalled()
    const [, payload] = setItem.mock.calls.at(-1)

    expect(payload.id).toBe('statement-1')
    expect(payload.relationships.assignee.data).toEqual({ type: 'AssignableUser', id: 'user-2' })
  })

  it('clears the assignee relationship when the modal reports an unassignment', () => {
    const wrapper = mountComponent()

    wrapper.vm.handleEntityAssigned({ entityId: 'statement-1', assignee: { id: '' } })

    const [, payload] = setItem.mock.calls.at(-1)

    expect(payload.relationships.assignee.data).toBeNull()
  })

  it('does nothing when the modal reports an assignment for a statement no longer on the page', () => {
    const wrapper = mountComponent()

    wrapper.vm.handleEntityAssigned({ entityId: 'unknown-statement', assignee: { id: 'user-2' } })

    expect(setItem).not.toHaveBeenCalled()
  })
})
