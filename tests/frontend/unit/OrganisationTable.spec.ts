/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { flushPromises, VueWrapper } from '@vue/test-utils'
import { Mock, vi } from 'vitest'
import { createStore } from 'vuex'
import OrganisationTable from '@DpJs/components/procedure/admin/InstitutionTagManagement/OrganisationTable.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({ fetchCustomFields: vi.fn(() => Promise.resolve([])) }),
}))

/*
 * Consider moving to client/js/types/ together with the InstitutionAttributes from InstitutionList.spec.ts
 * once InvitableToeb/InvitedToeb is migrated to API 3.0 (the field names name/legalName differ for now)
 */
interface InstitutionAttributes {
  hasActiveUsers?: boolean
  legalName: string
  participationFeedbackEmailAddress: string | null
}

const createItem = ({ id = 'orga-1', ...attributes }: { id?: string } & Partial<InstitutionAttributes> = {}) => ({
  id,
  attributes: {
    hasActiveUsers: true,
    legalName: 'Amt Nordwest',
    participationFeedbackEmailAddress: 'beteiligung@example.org',
    ...attributes,
  },
  relationships: {},
})

// Renders the name cell and the expanded content per item so they can be asserted without the real table
const DpDataTableStub = {
  props: ['items'],
  template: `
    <div>
      <div
        v-for="item in items"
        :key="item.id"
        class="row"
      >
        <slot name="legalName" v-bind="item" />
        <slot name="expandedContent" v-bind="item" />
      </div>
    </div>
  `,
}

// DpInlineNotification takes data-cy as a prop, which the auto-stub does not render as attribute
const DpInlineNotificationStub = {
  props: ['dataCy', 'message'],
  template: '<div :data-cy="dataCy">{{ message }}</div>',
}

describe('OrganisationTable', () => {
  let list: Mock
  let wrapper: VueWrapper<any>

  const mountTable = (items: ReturnType<typeof createItem>[], resourceType: 'InvitableToeb' | 'InvitedToeb' = 'InvitedToeb') => {
    list = vi.fn(() => ({ meta: { pagination: { currentPage: 1, perPage: 50, total: items.length, totalPages: 1 } } }))
    const emptyModule = { namespaced: true, state: () => ({ items: {} }), actions: { list: vi.fn(() => ({})) } }
    const store = createStore({
      modules: {
        FilterFlyout: { namespaced: true, getters: { getFilterQuery: () => ({}) } },
        InstitutionLocationContact: emptyModule,
        InstitutionTag: emptyModule,
        InstitutionTagCategory: emptyModule,
        [resourceType]: {
          namespaced: true,
          state: () => ({ items: Object.fromEntries(items.map(item => [item.id, item])) }),
          actions: { list },
        },
      },
    })

    wrapper = shallowMountWithGlobalMocks(OrganisationTable, {
      props: {
        headerFields: [{ field: 'legalName', label: 'Institution' }],
        procedureId: 'procedure-1',
        resourceType,
      },
      global: {
        plugins: [store],
        renderStubDefaultSlot: true,
        stubs: {
          'dp-data-table': DpDataTableStub,
          'dp-inline-notification': DpInlineNotificationStub,
        },
      },
    })
  }

  const findRow = (name: string) => wrapper.findAll('.row').find(row => row.text().includes(name))

  it('displays noActiveUsersBadge only for institutions without active users', async () => {
    mountTable([
      createItem({ id: 'orga-1', hasActiveUsers: true, legalName: 'Amt mit Nutzer*innen' }),
      createItem({ id: 'orga-2', hasActiveUsers: false, legalName: 'Amt Nordwest' }),
    ])
    await flushPromises()

    const withUsersRow = findRow('Amt mit Nutzer*innen')
    const noUsersRow = findRow('Amt Nordwest')

    expect(withUsersRow.find('[data-cy="organisationTable:noActiveUsersBadge"]').exists()).toBe(false)
    expect(withUsersRow.find('[data-cy="organisationTable:noActiveUsersHint"]').exists()).toBe(false)
    expect(noUsersRow.find('[data-cy="organisationTable:noActiveUsersBadge"]').exists()).toBe(true)
    expect(noUsersRow.find('[data-cy="organisationTable:noActiveUsersHint"]').exists()).toBe(true)
  })

  it('does not display noActiveUsersBadge for institutions whose flag is not loaded', async () => {
    mountTable([createItem({ hasActiveUsers: undefined })])
    await flushPromises()

    expect(wrapper.findAll('[data-cy="organisationTable:noActiveUsersBadge"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-cy="organisationTable:noActiveUsersHint"]')).toHaveLength(0)
  })

  it('explains in the invited list that the institution will still be invited', async () => {
    mountTable([createItem({ hasActiveUsers: false })], 'InvitedToeb')
    await flushPromises()

    expect(wrapper.find('[data-cy="organisationTable:noActiveUsersHint"]').text()).toBe('invitable_institution.users.active.none.hint.invited')
  })

  it('explains in the add list that the institution can still be invited', async () => {
    mountTable([createItem({ hasActiveUsers: false })], 'InvitableToeb')
    await flushPromises()

    expect(wrapper.find('[data-cy="organisationTable:noActiveUsersHint"]').text()).toBe('invitable_institution.users.active.none.hint.invitable')
  })

  it('shows only the badge, not the hint, for institutions without email', async () => {
    mountTable([createItem({ hasActiveUsers: false, participationFeedbackEmailAddress: null })])
    await flushPromises()

    expect(wrapper.find('[data-cy="organisationTable:noActiveUsersBadge"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-cy="organisationTable:noActiveUsersHint"]')).toHaveLength(0)
  })

  it('requests the hasActiveUsers flag from the api', async () => {
    mountTable([createItem()])
    await flushPromises()

    const requestedFields = list.mock.calls[0][1].fields.InvitedToeb.split(',')

    expect(requestedFields).toContain('hasActiveUsers')
  })
})
