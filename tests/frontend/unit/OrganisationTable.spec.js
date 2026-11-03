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
import OrganisationTable from '@DpJs/components/procedure/admin/InstitutionTagManagement/OrganisationTable'

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({ fetchCustomFields: vi.fn(() => Promise.resolve([])) }),
}))

/*
 * Renders only what this spec looks at: the cell of the institution name and the expanded content of each row.
 */
const DpDataTableStub = {
  props: ['items'],
  template: `
    <div>
      <div
        v-for="item in items"
        :key="item.id"
        class="row"
      >
        <div class="name"><slot name="legalName" v-bind="item" /></div>
        <div class="expanded"><slot name="expandedContent" v-bind="item" /></div>
      </div>
    </div>
  `,
}

const createItem = (id, legalName, hasActiveUsers) => ({
  id,
  attributes: { hasActiveUsers, legalName, participationFeedbackEmailAddress: 'beteiligung@example.org' },
  relationships: {},
})

describe('OrganisationTable', () => {
  const mountTable = (resourceType) => {
    const list = vi.fn(() => ({ meta: { pagination: { currentPage: 1, perPage: 50, total: 2, totalPages: 1 } } }))
    const emptyModule = { namespaced: true, state: () => ({ items: {} }), actions: { list: vi.fn(() => ({})) } }
    const store = createStore({
      modules: {
        FilterFlyout: { namespaced: true, getters: { getFilterQuery: () => ({}) } },
        InstitutionLocationContact: emptyModule,
        InstitutionTag: emptyModule,
        InstitutionTagCategory: emptyModule,
        [resourceType]: {
          namespaced: true,
          state: () => ({
            items: {
              withUsers: createItem('withUsers', 'Amt mit Nutzer*innen', true),
              withoutUsers: createItem('withoutUsers', 'Amt Nordwest', false),
            },
          }),
          actions: { list },
        },
      },
    })

    const wrapper = mount(OrganisationTable, {
      global: {
        plugins: [store],
        stubs: { ClientSideTagFilter: true, DpDataTable: DpDataTableStub, DpPager: true, DpSearchField: true },
      },
      props: {
        headerFields: [{ field: 'legalName', label: 'Institution' }],
        procedureId: 'procedureId',
        resourceType,
      },
    })

    return { list, wrapper }
  }

  it('marks only institutions without active users', async () => {
    const { wrapper } = mountTable('InvitedToeb')

    await flushPromises()

    const rows = wrapper.findAll('.row')
    const noUsersRow = rows.find(row => row.text().includes('Amt Nordwest'))
    const withUsersRow = rows.find(row => row.text().includes('Amt mit Nutzer*innen'))

    expect(noUsersRow.find('.name').text()).toContain('invitable_institution.users.active.none')
    expect(withUsersRow.find('.name').text()).not.toContain('invitable_institution.users.active.none')
    expect(noUsersRow.find('.expanded').text()).toContain('invitable_institution.users.active.none.hint.invited')
    expect(withUsersRow.find('.expanded').text()).not.toContain('invitable_institution.users.active.none.hint')
  })

  it('explains in the add list that the institution can still be invited', async () => {
    const { wrapper } = mountTable('InvitableToeb')

    await flushPromises()

    const noUsersRow = wrapper.findAll('.row').find(row => row.text().includes('Amt Nordwest'))

    expect(noUsersRow.find('.expanded').text()).toContain('invitable_institution.users.active.none.hint.invitable')
  })

  it('requests the flag from the api', async () => {
    const { list } = mountTable('InvitedToeb')

    await flushPromises()

    const requestedFields = list.mock.calls[0][1].fields.InvitedToeb.split(',')

    expect(requestedFields).toContain('hasActiveUsers')
  })
})
