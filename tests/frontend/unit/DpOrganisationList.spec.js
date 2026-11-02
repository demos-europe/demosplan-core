/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { createStore } from 'vuex'
import DpOrganisationList from '@DpJs/components/user/DpOrganisationList/DpOrganisationList'
import { flushPromises } from '@vue/test-utils'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const { mockDpApiGet, mockDpRpc } = vi.hoisted(() => ({ mockDpApiGet: vi.fn(), mockDpRpc: vi.fn() }))

vi.mock('@demos-europe/demosplan-ui', async importOriginal => {
  const original = await importOriginal()

  return {
    ...original,
    dpApi: Object.assign((...args) => original.dpApi(...args), { get: (...args) => mockDpApiGet(...args) }),
    dpRpc: (...args) => mockDpRpc(...args),
  }
})

const rpcResponse = (updatedIds, skippedIds = []) => Promise.resolve({
  data: [{ jsonrpc: '2.0', id: 'x', result: { updatedIds, skippedIds } }],
})

// The component resolves permissions at import time via the demosplan-ui helper, which reads this map
vi.hoisted(() => {
  globalThis.dplan.permissions = {
    feature_orga_delete: true,
    field_organisation_two_factor_required: true,
  }
})

const items = {
  'orga-1': { id: 'orga-1', type: 'Orga', attributes: { name: 'Alpha', twoFactorRequired: false } },
  'orga-2': { id: 'orga-2', type: 'Orga', attributes: { name: 'Beta', twoFactorRequired: true } },
}

const listModule = (state = {}) => ({
  namespaced: true,
  state: () => ({ items: {}, currentPage: 1, totalPages: 1, ...state }),
  actions: {
    list: vi.fn(() => Promise.resolve()),
    delete: vi.fn(() => Promise.resolve()),
  },
})

const DpModalStub = {
  name: 'DpModal',
  template: '<div><slot name="header" /><slot /><slot name="footer" /></div>',
  methods: {
    toggle: vi.fn(),
  },
}

const apiPage = (data, totalPages = 1) => Promise.resolve({
  data: { data, meta: { pagination: { total_pages: totalPages } } },
})

// Answers like the API: a manual selection is requested by id, select-all by the list filter
const apiOrganisations = (organisations = Object.values(items)) => (url, params) => {
  const ids = params.filter.selectedIds?.condition.value

  return apiPage(ids ? organisations.filter(organisation => ids.includes(organisation.id)) : organisations)
}

let orgaModule

const lastListFilter = () => orgaModule.actions.list.mock.lastCall[1].filter

const mountList = (totalCount = 2) => {
  orgaModule = listModule({ items })
  orgaModule.actions.list.mockResolvedValue({ meta: { pagination: { total: totalCount } } })

  const store = createStore({
    modules: {
      Department: listModule(),
      Orga: {
        ...orgaModule,
        modules: { Pending: listModule() },
      },
      Role: listModule(),
    },
  })

  return shallowMountWithGlobalMocks(DpOrganisationList, {
    global: {
      plugins: [store],
      stubs: {
        DpModal: DpModalStub,
        DpTransitionExpand: { template: '<div><slot /></div>' },
      },
    },
  })
}

describe('DpOrganisationList', () => {
  beforeEach(() => {
    mockDpRpc.mockImplementation(() => rpcResponse(['orga-1', 'orga-2']))
    mockDpApiGet.mockImplementation(apiOrganisations())
    vi.stubGlobal('dpconfirm', vi.fn(() => true))
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
  })

  it('tracks single selections with the organisation name', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()

    wrapper.vm.toggleOne('orga-1')

    expect(wrapper.vm.selectedCount).toBe(1)
    expect(wrapper.vm.selectedOrganisationsList).toEqual([{ id: 'orga-1', name: 'Alpha', twoFactorRequired: false }])

    wrapper.vm.toggleOne('orga-1')

    expect(wrapper.vm.selectedCount).toBe(0)
  })

  it('selects every matching organisation across all pages without loading them', async () => {
    const wrapper = mountList(30)

    await flushPromises()
    wrapper.vm.toggleAll(true)

    expect(wrapper.vm.isAllSelected).toBe(true)
    expect(wrapper.vm.selectedCount).toBe(30)
    expect(wrapper.vm.isSelected('orga-1')).toBe(true)
    expect(mockDpApiGet).not.toHaveBeenCalled()

    wrapper.vm.toggleOne('orga-1')

    expect(wrapper.vm.isAllSelected).toBe(false)
    expect(wrapper.vm.isSelected('orga-1')).toBe(false)
    expect(wrapper.vm.selectedCount).toBe(29)

    wrapper.vm.toggleAll(false)

    expect(wrapper.vm.selectedCount).toBe(0)
  })

  it('loads the selected organisations with the current filter before the modal opens', async () => {
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.handleTwoFactorFilter(true)
    wrapper.vm.toggleAll(true)
    wrapper.vm.toggleOne('orga-1')

    await wrapper.vm.openTwoFactorModal()

    const [, params] = mockDpApiGet.mock.lastCall

    expect(params.fields).toEqual({ Orga: 'name,twoFactorRequired' })
    expect(params.filter.withoutTwoFactor.condition.value).toBe(false)
    expect(wrapper.vm.selectedOrganisationsList).toEqual([{ id: 'orga-2', name: 'Beta', twoFactorRequired: true }])
    expect(wrapper.vm.twoFactorTarget).toBe(true)
    expect(DpModalStub.methods.toggle).toHaveBeenCalledTimes(1)
  })

  it('opens the modal with the saved values, not the ones from when the organisation was selected', async () => {
    const wrapper = mountList()

    await flushPromises()
    // Alpha is selected with 2FA off and then switched on in its own form
    wrapper.vm.toggleOne('orga-1')
    mockDpApiGet.mockImplementation(apiOrganisations([
      { ...items['orga-1'], attributes: { name: 'Alpha', twoFactorRequired: true } },
    ]))

    await wrapper.vm.openTwoFactorModal()

    expect(mockDpApiGet.mock.lastCall[1].filter.selectedIds.condition).toEqual({
      path: 'id',
      operator: 'IN',
      value: ['orga-1'],
    })
    expect(wrapper.vm.twoFactorTarget).toBe(true)

    wrapper.vm.twoFactorTarget = false

    expect(wrapper.vm.isTwoFactorChoiceChanged).toBe(true)
  })

  it('pages through all matching organisations', async () => {
    mockDpApiGet
      .mockImplementationOnce(() => apiPage([items['orga-1']], 2))
      .mockImplementationOnce(() => apiPage([items['orga-2']], 2))
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.toggleAll(true)

    const ids = await wrapper.vm.resolveSelectedIds()

    expect(mockDpApiGet.mock.calls.map(([, params]) => params.page.number)).toEqual([1, 2])
    expect(ids).toEqual(['orga-1', 'orga-2'])
  })

  it('drops the select-all state when the filter changes', async () => {
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.toggleAll(true)
    wrapper.vm.handleSearch('Alp')

    expect(wrapper.vm.isAllSelected).toBe(false)
    expect(wrapper.vm.selectedCount).toBe(0)
  })

  it('asks with the count and deletes every selected organisation', async () => {
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.toggleAll(true)

    await wrapper.vm.deleteItems()

    expect(dpconfirm).toHaveBeenCalledWith('check.organisations.delete')
    expect(orgaModule.actions.delete).toHaveBeenCalledTimes(2)
    expect(wrapper.vm.selectedCount).toBe(0)
  })

  it('deletes at most five organisations at a time', async () => {
    const manyOrganisations = Array.from({ length: 12 }, (_, idx) => ({
      id: `orga-${idx}`,
      type: 'Orga',
      attributes: { name: `Orga ${idx}`, twoFactorRequired: false },
    }))

    mockDpApiGet.mockImplementation(apiOrganisations(manyOrganisations))
    const wrapper = mountList(12)
    let running = 0
    let maxRunning = 0

    orgaModule.actions.delete.mockImplementation(async () => {
      running++
      maxRunning = Math.max(maxRunning, running)
      await Promise.resolve()
      running--
    })

    await flushPromises()
    wrapper.vm.toggleAll(true)

    await wrapper.vm.deleteItems()

    expect(orgaModule.actions.delete).toHaveBeenCalledTimes(12)
    expect(maxRunning).toBe(5)
  })

  it('shows the selection card only once something is selected', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()

    expect(wrapper.findComponent({ name: 'SelectionCard' }).exists()).toBe(false)

    wrapper.vm.toggleOne('orga-2')
    await wrapper.vm.$nextTick()

    expect(wrapper.findComponent({ name: 'SelectionCard' }).exists()).toBe(true)
  })

  it('flags a selection with differing two-factor values', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleOne('orga-1')

    expect(wrapper.vm.hasMixedTwoFactorValues).toBe(false)

    wrapper.vm.toggleOne('orga-2')

    expect(wrapper.vm.hasMixedTwoFactorValues).toBe(true)
  })

  it('opens the modal only when something is selected', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    await wrapper.vm.openTwoFactorModal()

    expect(DpModalStub.methods.toggle).not.toHaveBeenCalled()

    wrapper.vm.toggleOne('orga-1')
    await wrapper.vm.openTwoFactorModal()

    expect(DpModalStub.methods.toggle).toHaveBeenCalledTimes(1)
  })

  it('preselects the shared value and nothing for a mixed selection', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleOne('orga-2')
    await wrapper.vm.openTwoFactorModal()

    expect(wrapper.vm.twoFactorTarget).toBe(true)

    wrapper.vm.toggleOne('orga-1')
    await wrapper.vm.openTwoFactorModal()

    expect(wrapper.vm.twoFactorTarget).toBeNull()
  })

  it('patches every selected organisation with the chosen value and reports once', async () => {
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.toggleAll(true)
    wrapper.vm.twoFactorTarget = true

    await wrapper.vm.applyTwoFactorRequired()

    expect(mockDpRpc).toHaveBeenCalledTimes(1)
    expect(mockDpRpc).toHaveBeenCalledWith('organisations.bulk.twoFactorRequired', {
      organisationIds: ['orga-1', 'orga-2'],
      twoFactorRequired: true,
    })
    expect(dplan.notify.notify).toHaveBeenCalledTimes(1)
    expect(dplan.notify.notify).toHaveBeenCalledWith('confirm', '2fa.required.updated')
    expect(DpModalStub.methods.toggle).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.selectedCount).toBe(0)
  })

  it('does nothing while no value has been chosen', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleOne('orga-1')

    await wrapper.vm.applyTwoFactorRequired()

    expect(mockDpRpc).not.toHaveBeenCalled()
    expect(wrapper.vm.selectedCount).toBe(1)
  })

  it('treats the preselected shared value as unchanged', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleOne('orga-1')
    await wrapper.vm.openTwoFactorModal()

    expect(wrapper.vm.isTwoFactorChoiceChanged).toBe(false)

    wrapper.vm.twoFactorTarget = true

    expect(wrapper.vm.isTwoFactorChoiceChanged).toBe(true)

    wrapper.vm.toggleOne('orga-2')
    wrapper.vm.twoFactorTarget = true

    expect(wrapper.vm.isTwoFactorChoiceChanged).toBe(true)
  })

  it('forgets the chosen value when the modal closes', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.twoFactorTarget = false

    wrapper.vm.onTwoFactorModalToggled(false)

    expect(wrapper.vm.twoFactorTarget).toBeNull()
  })

  it('reports skipped organisations separately from updated ones', async () => {
    mockDpRpc.mockImplementation(() => rpcResponse(['orga-1'], ['orga-2']))
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.toggleAll(true)
    wrapper.vm.twoFactorTarget = false

    await wrapper.vm.applyTwoFactorRequired()

    expect(dplan.notify.notify).toHaveBeenCalledWith('confirm', '2fa.required.updated')
    expect(dplan.notify.notify).toHaveBeenCalledWith('error', '2fa.required.update.failed')
  })

  it('reports every organisation as failed when the request fails', async () => {
    mockDpRpc.mockImplementation(() => Promise.reject(new Error('boom')))
    const wrapper = mountList()

    await flushPromises()
    wrapper.vm.toggleAll(true)
    wrapper.vm.twoFactorTarget = false

    await wrapper.vm.applyTwoFactorRequired()

    expect(dplan.notify.notify).toHaveBeenCalledTimes(1)
    expect(dplan.notify.notify).toHaveBeenCalledWith('error', '2fa.required.update.failed')
  })

  it('requests only organisations without the two-factor requirement while the toggle is on', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.handleTwoFactorFilter(true)

    expect(orgaModule.actions.list.mock.lastCall[1].page).toEqual({ number: 1 })
    expect(lastListFilter().withoutTwoFactor).toEqual({
      condition: { path: 'twoFactorRequired', value: false },
    })

    wrapper.vm.handleTwoFactorFilter(false)

    expect(lastListFilter().withoutTwoFactor).toBeUndefined()
  })

  it('combines the two-factor toggle with search term and organisation type', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.handleTwoFactorFilter(true)
    wrapper.vm.handleSearch('Alp')
    wrapper.vm.handleFilter({ municipality: true, other: false })

    const filter = lastListFilter()

    expect(filter.namefilter.condition.value).toBe('Alp')
    expect(filter.municipality.condition).toEqual({
      path: 'statusInCustomers.orgaType.name',
      value: 'municipality',
      memberOf: 'orgaType',
    })
    expect(filter.other).toBeUndefined()
    expect(filter.withoutTwoFactor.condition.value).toBe(false)
  })
})
