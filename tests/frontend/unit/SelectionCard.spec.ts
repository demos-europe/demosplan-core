/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { mount } from '@vue/test-utils'
import SelectionCard from '@DpJs/components/shared/SelectionCard.vue'

const items = [
  { id: 'item-1', name: 'First Item' },
  { id: 'item-2', name: 'Second Item' },
]

const mountCard = (props = {}, slots = {}) => mount(SelectionCard, {
  props: {
    countText: '2 selected',
    items,
    showItems: true,
    ...props,
  },
  slots,
  global: {
    stubs: {
      DpButton: {
        template: `
          <button @click="$emit('click')">
            <slot />
          </button>
        `,
      },
      DpLoading: {
        template: `
          <div data-testid="loading">
            Loading...
          </div>
        `
      },
      DpTransitionExpand: { template: '<div><slot /></div>' },
    },
  },
})

describe('SelectionCard', () => {
  it('renders the count and the item names by default', () => {
    const wrapper = mountCard()

    expect(wrapper.find('[data-cy="selectionCard:count"]').text()).toBe('2 selected')
    expect(wrapper.text()).toContain('First Item')
    expect(wrapper.text()).toContain('Second Item')
  })

  it('lets the parent render the rows via the item slot', () => {
    const wrapper = mountCard({}, {
      item: '<template #item="{ item }"><em>{{ item.id }}</em></template>',
    })

    expect(wrapper.findAll('em').map(el => el.text())).toEqual(['item-1', 'item-2'])
  })

  it('emits the id of a removed item', async () => {
    const wrapper = mountCard()

    await wrapper.findAll('[data-cy="selectionCard:removeItem"]')[1].trigger('click')

    expect(wrapper.emitted('remove:item')).toEqual([['item-2']])
  })

  it('emits reset and toggle events', async () => {
    const wrapper = mountCard()

    await wrapper.find('[data-cy="selectionCard:reset"]').trigger('click')
    await wrapper.find('[data-cy="selectionCard:toggleItems"]').trigger('click')

    expect(wrapper.emitted('reset')).toHaveLength(1)
    expect(wrapper.emitted('toggle:items')).toHaveLength(1)
  })

  it('shows a spinner instead of the rows while loading', () => {
    const wrapper = mountCard({ isLoadingItems: true })

    expect(wrapper.find('[data-testid="loading"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('First Item')
  })
})
