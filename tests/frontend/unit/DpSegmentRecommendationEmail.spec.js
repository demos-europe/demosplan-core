/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { createStore } from 'vuex'
import DpSegmentRecommendationEmail from '@DpJs/components/statement/statement/DpSegmentRecommendationEmail'
import { nextTick } from 'vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

/*
 * The component reaches the application event bus through this composable. Jest runs against
 * plain Vue 3 (jest.config.js removes the @vue/compat alias), where $on does not exist, so the
 * composable is replaced by one that hands the registered handlers back to the test.
 */
const mockRootEventHandlers = {}
const mockEmitRootEvent = jest.fn()

jest.mock('@DpJs/composables/useRootEventBus', () => ({
  useRootEventBus: () => ({
    emitRootEvent: mockEmitRootEvent,
    onRootEvent: (event, handler) => {
      mockRootEventHandlers[event] = handler
    },
  }),
}))

const mockDpRpc = jest.fn(() => Promise.resolve())

jest.mock('@demos-europe/demosplan-ui', () => ({
  ...jest.requireActual('@demos-europe/demosplan-ui'),
  dpRpc: (...args) => mockDpRpc(...args),
}))

describe('DpSegmentRecommendationEmail', () => {
  const SEGMENT_WITH_RECOMMENDATION = {
    id: 'segment-1',
    attributes: {
      externId: 'M7-2',
      recommendation: '<p>Recommendation</p>',
      text: '<p>Segment text</p>',
    },
  }

  const SEGMENT_WITHOUT_RECOMMENDATION = {
    id: 'segment-2',
    attributes: {
      externId: 'M7-3',
      recommendation: '',
      text: '<p>Another segment</p>',
    },
  }

  let wrapper

  const openFormFor = async (segmentId, externId) => {
    mockRootEventHandlers['segment:send-via-mail'](segmentId, externId)

    await nextTick()
  }

  /*
   * Children are stubbed by shallowMount, so fields are addressed as components and their
   * v-model is driven by emitting the event the parent listens to.
   */
  const findField = id => wrapper.findComponent(`#${id}`)

  const setField = async (id, value) => {
    findField(id).vm.$emit('update:modelValue', value)

    await nextTick()
  }

  beforeEach(() => {
    mockDpRpc.mockClear()
    mockEmitRootEvent.mockClear()

    const store = createStore({
      modules: {
        StatementSegment: {
          namespaced: true,
          state: () => ({
            items: {
              'segment-1': SEGMENT_WITH_RECOMMENDATION,
              'segment-2': SEGMENT_WITHOUT_RECOMMENDATION,
            },
          }),
        },
      },
    })

    wrapper = shallowMountWithGlobalMocks(DpSegmentRecommendationEmail, {
      props: {
        currentUserEmail: 'planner@example.org',
        procedureName: 'Test procedure',
      },
      global: {
        plugins: [store],
        /*
         * The checkboxes sit in the accordion's titlePrefix slot and the editors in its default
         * slot. The default stub drops both, and the real component cannot be used because the
         * built library carries its own Vue copy, so a stub that just renders the slots is used.
         */
        stubs: {
          'dp-accordion': {
            template: '<div><slot name="titlePrefix" /><slot /></div>',
          },
        },
      },
    })
  })

  it('stays hidden until the form is opened through the event bus', async () => {
    expect(wrapper.find('#recipient').exists()).toBe(false)

    await openFormFor('segment-1', 'M7-2')

    expect(wrapper.find('#recipient').exists()).toBe(true)
  })

  it('prefills the reply-to address with the address of the current user', async () => {
    await openFormFor('segment-1', 'M7-2')

    expect(findField('replyToEmail').props('modelValue')).toBe('planner@example.org')
  })

  it('takes segment text and recommendation from the store', async () => {
    await openFormFor('segment-1', 'M7-2')

    const editors = wrapper.findAllComponents({ name: 'DpEditor' })

    expect(editors[0].props('value')).toBe('<p>Segment text</p>')
    expect(editors[1].props('value')).toBe('<p>Recommendation</p>')
  })

  it('disables the recommendation checkbox if the segment has none', async () => {
    await openFormFor('segment-2', 'M7-3')

    expect(findField('attachRecommendation').props('disabled')).toBe(true)
  })

  it('sends message and segment text, but not the unchecked recommendation', async () => {
    await openFormFor('segment-1', 'M7-2')
    await setField('recipient', 'external@example.org')
    await setField('message', 'Please have a look')

    wrapper.findComponent({ name: 'DpButtonRow' }).vm.$emit('primary-action')

    expect(mockDpRpc).toHaveBeenCalledWith('segment.email.sender', expect.objectContaining({
      body: 'Please have a look\n\n<p>Segment text</p>',
      recipientMail: 'external@example.org',
      replyTo: 'planner@example.org',
      segmentIds: ['segment-1'],
    }))
  })

  it('appends the recommendation once its checkbox is checked', async () => {
    await openFormFor('segment-1', 'M7-2')
    await setField('message', 'Please have a look')
    await setField('attachRecommendation', true)

    wrapper.findComponent({ name: 'DpButtonRow' }).vm.$emit('primary-action')

    expect(mockDpRpc).toHaveBeenCalledWith('segment.email.sender', expect.objectContaining({
      body: 'Please have a look\n\n<p>Segment text</p>\n\n<p>Recommendation</p>',
    }))
  })

  /*
   * The component is mounted once and reused for every segment, so anything the user typed
   * for the previous one has to be gone when the form opens again.
   */
  it('does not carry entries over to the next segment', async () => {
    await openFormFor('segment-1', 'M7-2')
    await setField('recipient', 'external@example.org')
    await setField('attachSegmentText', false)

    await openFormFor('segment-2', 'M7-3')

    wrapper.findComponent({ name: 'DpButtonRow' }).vm.$emit('primary-action')

    /*
     * An empty recipient and a body holding nothing but the segment text of the second segment
     * show that the entries were cleared and the checkboxes are back at their defaults.
     */
    expect(mockDpRpc).toHaveBeenCalledWith('segment.email.sender', expect.objectContaining({
      body: '<p>Another segment</p>',
      recipientMail: '',
      segmentIds: ['segment-2'],
    }))
  })
})
