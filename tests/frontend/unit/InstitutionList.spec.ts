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
import InstitutionList from '@DpJs/components/procedure/admin/InstitutionTagManagement/InstitutionList.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

vi.mock('@DpJs/composables/useCustomFields', () => ({
  useCustomFields: () => ({
    fetchCustomFields: vi.fn(() => Promise.resolve([])),
    updateCustomFields: vi.fn(() => Promise.resolve()),
  }),
}))

interface InstitutionAttributes {
  createdDate: string
  hasActiveUsers?: boolean
  name: string
  participationFeedbackEmailAddress: string | null
}

const createItem = ({ id = 'orga-1', ...attributes }: { id?: string } & Partial<InstitutionAttributes> = {}) => ({
  id,
  attributes: {
    createdDate: '2022-12-09T00:00:00+00:00',
    hasActiveUsers: true,
    name: 'Hummel ewf',
    participationFeedbackEmailAddress: null,
    ...attributes,
  },
  relationships: { assignedTags: { data: [] } },
})

// Renders the name cell per item so it can be asserted without the real table
const DpDataTableStub = {
  props: ['items'],
  template: `
    <div>
      <div
        v-for="item in items"
        :key="item.id"
        class="row"
      >
        <slot name="name" v-bind="item" />
      </div>
    </div>
  `,
}

describe('InstitutionList', () => {
  let list: Mock
  let wrapper: VueWrapper<any>

  const mountList = (items: ReturnType<typeof createItem>[]) => {
    list = vi.fn(() => ({ meta: { totalItems: items.length, itemsPerPage: 50, currentPage: 1 } }))
    const emptyModule = { namespaced: true, state: () => ({ items: {} }), actions: { list: vi.fn(() => ({})) } }
    const store = createStore({
      modules: {
        InstitutionTag: emptyModule,
        InstitutionTagCategory: emptyModule,
        InvitableInstitution: {
          namespaced: true,
          state: () => ({ items: Object.fromEntries(items.map(item => [item.id, item])) }),
          actions: { list },
        },
      },
    })

    wrapper = shallowMountWithGlobalMocks(InstitutionList, {
      props: { isActive: true },
      global: {
        plugins: [store],
        renderStubDefaultSlot: true,
        stubs: {
          'dp-data-table': DpDataTableStub,
        },
      },
    })
  }

  const findRow = (name: string) => wrapper.findAll('.row').find(row => row.text().includes(name))

  beforeAll(() => {
    // Jsdom has no ResizeObserver, which the table scrollbar mixin of the component uses
    vi.stubGlobal('ResizeObserver', class {
      disconnect () {}

      observe () {}

      unobserve () {}
    })
  })

  afterAll(() => {
    vi.unstubAllGlobals()
  })

  it('marks only institutions without active users', async () => {
    mountList([
      createItem({ id: 'orga-1', hasActiveUsers: true, name: 'Firma mit Nutzer*innen' }),
      createItem({ id: 'orga-2', hasActiveUsers: false, name: 'Hummel ewf' }),
    ])
    await flushPromises()

    expect(findRow('Firma mit Nutzer*innen').find('[data-cy="institutionList:noActiveUsersBadge"]').exists()).toBe(false)
    expect(findRow('Hummel ewf').find('[data-cy="institutionList:noActiveUsersBadge"]').exists()).toBe(true)
  })

  it('does not mark institutions whose flag is not loaded', async () => {
    mountList([createItem({ hasActiveUsers: undefined })])
    await flushPromises()

    expect(wrapper.findAll('[data-cy="institutionList:noActiveUsersBadge"]')).toHaveLength(0)
  })

  it('requests the flag from the api', async () => {
    mountList([createItem()])
    await flushPromises()

    const requestedFields = list.mock.calls[0][1].fields.InvitableInstitution.split(',')

    expect(requestedFields).toContain('hasActiveUsers')
  })
})
