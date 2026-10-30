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
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

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

const mountList = () => {
  const store = createStore({
    modules: {
      Department: listModule(),
      Orga: {
        ...listModule({ items }),
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
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: true, status: 200 })))
    globalThis.dplan.csrfToken = 'csrf'
    globalThis.dplan.jwtToken = 'jwt'
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

  it('selects and deselects every organisation on the page', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()

    wrapper.vm.toggleAll(true)

    expect(wrapper.vm.allOnPageSelected).toBe(true)
    expect(wrapper.vm.selectedIds).toEqual(['orga-1', 'orga-2'])

    wrapper.vm.toggleAll(false)

    expect(wrapper.vm.selectedCount).toBe(0)
  })

  it('shows the selection card only once something is selected', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()

    expect(wrapper.findComponent({ name: 'DpSelectionCard' }).exists()).toBe(false)

    wrapper.vm.toggleOne('orga-2')
    await wrapper.vm.$nextTick()

    expect(wrapper.findComponent({ name: 'DpSelectionCard' }).exists()).toBe(true)
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
    wrapper.vm.openTwoFactorModal()

    expect(DpModalStub.methods.toggle).not.toHaveBeenCalled()

    wrapper.vm.toggleOne('orga-1')
    wrapper.vm.openTwoFactorModal()

    expect(DpModalStub.methods.toggle).toHaveBeenCalledTimes(1)
  })

  it('preselects the shared value and nothing for a mixed selection', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleOne('orga-2')
    wrapper.vm.openTwoFactorModal()

    expect(wrapper.vm.twoFactorTarget).toBe(true)

    wrapper.vm.toggleOne('orga-1')
    wrapper.vm.openTwoFactorModal()

    expect(wrapper.vm.twoFactorTarget).toBeNull()
  })

  it('patches every selected organisation with the chosen value and reports once', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleAll(true)
    wrapper.vm.twoFactorTarget = true

    await wrapper.vm.applyTwoFactorRequired()

    expect(fetch).toHaveBeenCalledTimes(2)
    const [url, options] = fetch.mock.calls[0]

    expect(url).toBe('organisation_update')
    expect(options.method).toBe('PATCH')
    expect(JSON.parse(options.body)).toEqual({
      data: { id: 'orga-1', type: 'Orga', attributes: { twoFactorRequired: true } },
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

    expect(fetch).not.toHaveBeenCalled()
    expect(wrapper.vm.selectedCount).toBe(1)
  })

  it('treats the preselected shared value as unchanged', async () => {
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleOne('orga-1')
    wrapper.vm.openTwoFactorModal()

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

  it('reports failures separately from successes', async () => {
    fetch
      .mockResolvedValueOnce({ ok: true, status: 200 })
      .mockResolvedValueOnce({ ok: false, status: 403 })
    const wrapper = mountList()

    await wrapper.vm.$nextTick()
    wrapper.vm.toggleAll(true)
    wrapper.vm.twoFactorTarget = false

    await wrapper.vm.applyTwoFactorRequired()

    expect(dplan.notify.notify).toHaveBeenCalledWith('confirm', '2fa.required.updated')
    expect(dplan.notify.notify).toHaveBeenCalledWith('error', '2fa.required.update.failed')
  })
})
