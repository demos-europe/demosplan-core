/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { flushPromises, mount } from '@vue/test-utils'
import { createStore } from 'vuex'
import InstitutionList from '@DpJs/components/procedure/admin/InstitutionTagManagement/InstitutionList'

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({
    fetchCustomFields: vi.fn(() => Promise.resolve([])),
    updateCustomFields: vi.fn(() => Promise.resolve()),
  }),
}))

/*
 * Renders only what this spec looks at: the cell of the institution name.
 */
const DpDataTableStub = {
  props: ['items'],
  template: '<div><div v-for="item in items" :key="item.id" class="row"><slot name="name" v-bind="item" /></div></div>',
}

const createItem = (id, name, hasActiveUsers) => ({
  id,
  attributes: { createdDate: '2022-12-09T00:00:00+00:00', hasActiveUsers, name, participationFeedbackEmailAddress: null },
  relationships: { assignedTags: { data: [] } },
})

describe('InstitutionList', () => {
  beforeAll(() => {
    // Jsdom has no ResizeObserver, which the scrollbar mixin of the table uses
    vi.stubGlobal('ResizeObserver', class {
      disconnect () {}

      observe () {}

      unobserve () {}
    })
  })

  const mountList = () => {
    const list = vi.fn(() => ({ meta: { totalItems: 2, itemsPerPage: 50, currentPage: 1 } }))
    const emptyModule = { namespaced: true, state: () => ({ items: {} }), actions: { list: vi.fn(() => ({})) } }
    const store = createStore({
      modules: {
        InstitutionTag: emptyModule,
        InstitutionTagCategory: emptyModule,
        InvitableInstitution: {
          namespaced: true,
          state: () => ({
            items: {
              withUsers: createItem('withUsers', 'Firma mit Nutzer*innen', true),
              withoutUsers: createItem('withoutUsers', 'Hummel ewf', false),
            },
          }),
          actions: { list },
        },
      },
    })

    const wrapper = mount(InstitutionList, {
      global: {
        plugins: [store],
        stubs: {
          ClientSideTagFilter: true,
          DpColumnSelector: true,
          DpDataTable: DpDataTableStub,
          DpSearchField: true,
          DpSlidingPagination: true,
        },
      },
      props: { isActive: true },
    })

    return { list, wrapper }
  }

  it('marks only institutions without active users', async () => {
    const { wrapper } = mountList()

    await flushPromises()

    const rows = wrapper.findAll('.row')
    const noUsersRow = rows.find(row => row.text().includes('Hummel ewf'))
    const withUsersRow = rows.find(row => row.text().includes('Firma mit Nutzer*innen'))

    expect(noUsersRow.text()).toContain('invitable_institution.users.active.none')
    expect(withUsersRow.text()).not.toContain('invitable_institution.users.active.none')
  })

  it('requests the flag from the api', async () => {
    const { list } = mountList()

    await flushPromises()

    const requestedFields = list.mock.calls[0][1].fields.InvitableInstitution.split(',')

    expect(requestedFields).toContain('hasActiveUsers')
  })
})
