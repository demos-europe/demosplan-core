/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import DpSelectionCard from '@DpJs/components/shared/DpSelectionCard.vue'
import { mount } from '@vue/test-utils'

const items = [
  { id: 'a', name: 'Alpha' },
  { id: 'b', name: 'Beta' },
]

const mountCard = (props = {}, slots = {}) => mount(DpSelectionCard, {
  props: {
    countText: '2 selected',
    items,
    showItems: true,
    ...props,
  },
  slots,
  global: {
    stubs: {
      DpTransitionExpand: { template: '<div><slot /></div>' },
    },
  },
})

describe('DpSelectionCard', () => {
  it('renders the count and the item names by default', () => {
    const wrapper = mountCard()

    expect(wrapper.find('[data-cy="selectionCard:count"]').text()).toBe('2 selected')
    expect(wrapper.text()).toContain('Alpha')
    expect(wrapper.text()).toContain('Beta')
  })

  it('lets the parent render the rows via the item slot', () => {
    const wrapper = mountCard({}, {
      item: '<template #item="{ item }"><em>{{ item.id }}</em></template>',
    })

    expect(wrapper.findAll('em').map(el => el.text())).toEqual(['a', 'b'])
  })

  it('emits the id of a removed item', async () => {
    const wrapper = mountCard()

    await wrapper.findAll('[data-cy="selectionCard:removeItem"]')[1].trigger('click')

    expect(wrapper.emitted('remove:item')).toEqual([['b']])
  })

  it('emits reset and toggle events', async () => {
    const wrapper = mountCard()

    await wrapper.find('[data-cy="resetSelectedItems"]').trigger('click')
    await wrapper.find('[data-cy="selectionCard:toggleItems"]').trigger('click')

    expect(wrapper.emitted('reset')).toHaveLength(1)
    expect(wrapper.emitted('toggle:items')).toHaveLength(1)
  })

  it('shows a spinner instead of the rows while loading', () => {
    const wrapper = mountCard({ isLoadingItems: true })

    expect(wrapper.findComponent({ name: 'DpLoading' }).exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Alpha')
  })
})
