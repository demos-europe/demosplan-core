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
import BoilerplateUsageList from '@DpJs/components/procedure/admin/BoilerplateUsageList.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const { mockStore, unlockSegment } = vi.hoisted(() => ({
  mockStore: {
    state: {
      Place: {
        items: {
          'place-open': { id: 'place-open', attributes: { name: 'Erwiderung verfassen', locked: false } },
          'place-locked': { id: 'place-locked', attributes: { name: 'Abgeschlossen', locked: true } },
        },
      },
      AssignableUser: {
        items: {
          'user-1': { id: 'user-1', attributes: { firstname: 'Motoko', lastname: 'Kusanagi' } },
        },
      },
    },
    dispatch: vi.fn(() => Promise.resolve()),
  },
  // Resolves immediately so the component's onSuccess callback runs synchronously in tests
  unlockSegment: vi.fn((_payload: unknown, onSuccess: () => void) => {
    onSuccess()

    return Promise.resolve()
  }),
}))

vi.mock('vuex', () => ({ useStore: () => mockStore }))
vi.mock('@DpJs/composables/useSegmentUnlock', async () => {
  const { ref } = await import('vue')

  return {
    useSegmentUnlock: () => ({
      unlockModal: ref(null),
      openUnlockModal: vi.fn(),
      unlockSegment,
    }),
  }
})

interface UsageRow {
  id: string
  type: 'segment' | 'statement'
  externId: string
  statementId: string
  assigneeName: string | null
  placeId: string | null
  placeName: string | null
  locked: boolean
  recommendation: string
}

const createRow = (overrides: Partial<UsageRow> = {}): UsageRow => ({
  id: 'segment-1',
  type: 'segment',
  externId: 'M31-1',
  statementId: 'statement-1',
  assigneeName: 'Motoko Kusanagi',
  placeId: 'place-open',
  placeName: 'Erwiderung verfassen',
  locked: false,
  recommendation: '<p>Erwiderung</p>',
  ...overrides,
})

// Renders every named slot per item so the row content can be asserted without the real table
const DpDataTableStub = {
  props: ['items', 'headerFields'],
  template: `
    <div>
      <div
        v-for="item in items"
        :key="item.id"
        data-test="row"
      >
        <slot name="externId" v-bind="item" />
        <slot name="assigneeName" v-bind="item" />
        <slot name="placeName" v-bind="item" />
        <slot name="expandedContent" v-bind="item" />
      </div>
    </div>
  `,
}

const DpButtonStub = {
  props: ['disabled', 'text'],
  template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ text }}</button>',
}

const DpCheckboxStub = {
  props: ['checked', 'disabled', 'label'],
  emits: ['change'],
  template: '<input type="checkbox" :checked="checked" :disabled="disabled" @change="$emit(\'change\', $event.target.checked)">',
}

const SegmentUnlockModalStub = {
  props: ['assignableUsers', 'places'],
  emits: ['unlock'],
  template: '<div />',
  methods: { toggle: vi.fn() },
}

describe('BoilerplateUsageList', () => {
  let wrapper: VueWrapper<any>

  const mountList = (usages: UsageRow[]) => shallowMountWithGlobalMocks(BoilerplateUsageList, {
    props: {
      procedureId: 'procedure-1',
      usages,
    },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        'dp-button': DpButtonStub,
        'dp-checkbox': DpCheckboxStub,
        'dp-data-table': DpDataTableStub,
        // The auto-stub trips over DpTooltip's `nodeType` prop and logs warnings
        'dp-tooltip': { template: '<span><slot /></span>' },
        'segment-unlock-modal': SegmentUnlockModalStub,
      },
    },
  })

  const rows = () => wrapper.findAll('[data-test="row"]')

  beforeEach(() => {
    (globalThis.hasPermission as Mock).mockImplementation(() => true)
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('renders one row per usage with a link to the recommendation view', () => {
    wrapper = mountList([createRow(), createRow({ id: 'segment-2', externId: 'M31-2' })])

    expect(rows()).toHaveLength(2)

    const link = wrapper.find('[data-cy="boilerplateUsageList:link"]')

    expect(link.text()).toBe('M31-1')
    expect(link.attributes('href')).toBe('dplan_statement_segments_list#recommendation')
    expect(Routing.generate).toHaveBeenCalledWith('dplan_statement_segments_list', {
      procedureId: 'procedure-1',
      statementId: 'statement-1',
      segment: 'segment-1',
    })
  })

  it('renders the recommendation text in the expandable content', () => {
    wrapper = mountList([createRow({ recommendation: '<p>Mein Textbaustein</p>' })])

    expect(wrapper.find('[data-cy="boilerplateUsageList:recommendation"]').html()).toContain('Mein Textbaustein')
  })

  it('shows a placeholder for rows without assignee or place', () => {
    wrapper = mountList([createRow({ type: 'statement', assigneeName: null, placeId: null, placeName: null })])

    expect(rows()[0].text()).toContain('—')
  })

  it('puts the total and the locked count into the headline', () => {
    wrapper = mountList([createRow(), createRow({ id: 'segment-2', locked: true }), createRow({ id: 'segment-3', locked: true })])

    expect(Translator.trans).toHaveBeenCalledWith('boilerplate.usage.headline.locked', { count: '3', lockedCount: '2' })
  })

  it('uses the plain headline when nothing is locked', () => {
    wrapper = mountList([createRow()])

    expect(Translator.trans).toHaveBeenCalledWith('boilerplate.usage.headline', { count: '1' })
    expect(Translator.trans).not.toHaveBeenCalledWith('boilerplate.usage.headline.locked', expect.anything())
  })

  it('marks locked rows with an unlock button for lock administrators', () => {
    wrapper = mountList([createRow(), createRow({ id: 'segment-2', locked: true })])

    expect(wrapper.findAll('[data-cy="boilerplateUsageList:unlock"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-cy="boilerplateUsageList:lockIcon"]')).toHaveLength(0)
  })

  it('keeps the unlock button disabled until the modal options have loaded', async () => {
    wrapper = mountList([createRow({ locked: true })])

    const button = wrapper.find('[data-cy="boilerplateUsageList:unlock"]')

    expect(button.attributes('disabled')).toBeDefined()
    expect(wrapper.findComponent(SegmentUnlockModalStub).exists()).toBe(false)

    await flushPromises()

    expect(button.attributes('disabled')).toBeUndefined()
    expect(wrapper.findComponent(SegmentUnlockModalStub).exists()).toBe(true)
  })

  it('marks locked rows with a plain icon for users who may not unlock', () => {
    (globalThis.hasPermission as Mock).mockImplementation((permission: string) => permission !== 'feature_administrate_segment_lock')
    wrapper = mountList([createRow({ locked: true })])

    expect(wrapper.findAll('[data-cy="boilerplateUsageList:unlock"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-cy="boilerplateUsageList:lockIcon"]')).toHaveLength(1)
    expect(wrapper.findComponent(SegmentUnlockModalStub).exists()).toBe(false)
  })

  it('hides the lock filter when the lock feature is not granted', () => {
    (globalThis.hasPermission as Mock).mockImplementation((permission: string) => permission !== 'feature_segment_lock_by_workflow_place')
    wrapper = mountList([createRow({ locked: true })])

    expect(wrapper.find('[data-cy="boilerplateUsageList:onlyLocked"]').exists()).toBe(false)
  })

  it('disables the lock filter when no row is locked', () => {
    wrapper = mountList([createRow()])

    expect(wrapper.find('[data-cy="boilerplateUsageList:onlyLocked"]').attributes('disabled')).toBeDefined()
  })

  it('shows only locked rows while the filter is active', async () => {
    wrapper = mountList([createRow(), createRow({ id: 'segment-2', externId: 'M31-2', locked: true })])

    await wrapper.find('[data-cy="boilerplateUsageList:onlyLocked"]').setValue(true)

    expect(rows()).toHaveLength(1)
    expect(rows()[0].text()).toContain('M31-2')
  })

  it('updates the row after a successful unlock and drops the filter when nothing is locked anymore', async () => {
    wrapper = mountList([createRow(), createRow({ id: 'segment-2', externId: 'M31-2', locked: true, placeName: 'Abgeschlossen' })])
    await flushPromises()
    await wrapper.find('[data-cy="boilerplateUsageList:onlyLocked"]').setValue(true)
    await wrapper.find('[data-cy="boilerplateUsageList:unlock"]').trigger('click')

    wrapper.findComponent(SegmentUnlockModalStub).vm.$emit('unlock', {
      assignee: { id: 'noAssigneeId', name: 'not.assigned' },
      place: { id: 'place-open', name: 'Erwiderung verfassen', locked: false },
    })
    await flushPromises()

    expect(unlockSegment).toHaveBeenCalled()
    expect(rows()).toHaveLength(2)
    expect(rows()[1].text()).toContain('Erwiderung verfassen')
    expect(rows()[1].text()).not.toContain('Abgeschlossen')
    expect(wrapper.findAll('[data-cy="boilerplateUsageList:unlock"]')).toHaveLength(0)
  })

  it('applies a late unlock response to the row it was started for', async () => {
    wrapper = mountList([
      createRow({ id: 'segment-1', externId: 'M31-1', locked: true, placeName: 'Abgeschlossen' }),
      createRow({ id: 'segment-2', externId: 'M31-2', locked: true, placeName: 'Abgeschlossen' }),
    ])
    await flushPromises()

    // Hold back the first PATCH callback until a second unlock has been started
    let finishFirst: () => void = () => {}

    unlockSegment.mockImplementationOnce((_payload: unknown, onSuccess: () => void) => {
      finishFirst = onSuccess

      return Promise.resolve()
    })

    const payload = {
      assignee: { id: 'noAssigneeId', name: 'not.assigned' },
      place: { id: 'place-open', name: 'Erwiderung verfassen', locked: false },
    }
    const modal = wrapper.findComponent(SegmentUnlockModalStub)

    await wrapper.findAll('[data-cy="boilerplateUsageList:unlock"]')[0].trigger('click')
    modal.vm.$emit('unlock', payload)
    await wrapper.findAll('[data-cy="boilerplateUsageList:unlock"]')[1].trigger('click')
    finishFirst()
    await flushPromises()

    expect(rows()[0].text()).toContain('Erwiderung verfassen')
    expect(rows()[1].text()).toContain('Abgeschlossen')
  })

  it('loads places and assignable users for the unlock modal', async () => {
    wrapper = mountList([createRow({ locked: true })])
    await flushPromises()

    expect(mockStore.dispatch).toHaveBeenCalledWith('Place/list', expect.anything())
    expect(mockStore.dispatch).toHaveBeenCalledWith('AssignableUser/list', expect.anything())

    const modal = wrapper.findComponent(SegmentUnlockModalStub)

    expect(modal.props('places')).toEqual([
      { id: 'place-open', name: 'Erwiderung verfassen', locked: false },
      { id: 'place-locked', name: 'Abgeschlossen', locked: true },
    ])
    expect(modal.props('assignableUsers')).toEqual([
      { id: 'noAssigneeId', name: 'not.assigned' },
      { id: 'user-1', name: 'Motoko Kusanagi' },
    ])
  })

  it('shows the empty state without usages', () => {
    wrapper = mountList([])

    // Auto-stubs camel-case attributes, so the stub tag is the reliable hook here
    expect(wrapper.find('dp-inline-notification-stub').exists()).toBe(true)
    expect(wrapper.findComponent(DpDataTableStub).exists()).toBe(false)
  })
})
