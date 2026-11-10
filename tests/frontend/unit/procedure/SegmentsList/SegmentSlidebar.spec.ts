/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { describe, expect, it } from 'vitest'
import { createStore } from 'vuex'
import { mount } from '@vue/test-utils'
import SegmentSlidebar from '@DpJs/components/procedure/SegmentsList/SegmentSlidebar.vue'
import SegmentSlidebarStore from '@DpJs/store/procedure/SegmentSlidebar'

describe('SegmentSlidebar', () => {
  it('exposes the slidebar state to its slot', () => {
    const store = createStore({ modules: { SegmentSlidebar: { ...SegmentSlidebarStore, state: () => ({ ...SegmentSlidebarStore.state }) } } })

    store.commit('SegmentSlidebar/setContent', {
      prop: 'slidebar',
      val: { externId: 'S1', isOpen: true, segmentId: 'segment-1', showTab: 'history' },
    })

    const wrapper = mount(SegmentSlidebar, {
      global: {
        plugins: [store],
        stubs: { DpSlidebar: { template: '<div><slot /></div>' } },
      },
      slots: {
        default: `<template #default="{ slidebar }">
          <span>{{ slidebar.segmentId }} {{ slidebar.externId }} {{ slidebar.showTab }}</span>
        </template>`,
      },
    })

    expect(wrapper.text()).toBe('segment-1 S1 history')
  })
})
