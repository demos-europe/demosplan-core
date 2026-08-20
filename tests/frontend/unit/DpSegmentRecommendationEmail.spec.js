/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createStore } from 'vuex'
import DpSegmentRecommendationEmail from '@DpJs/components/statement/statement/DpSegmentRecommendationEmail'
import { nextTick } from 'vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const { mockDpRpc } = vi.hoisted(() => ({ mockDpRpc: vi.fn(() => Promise.resolve()) }))

/*
 * `validate` inspects the rendered form, but shallowMount leaves only stubs behind, whose
 * `required` and `type="email"` attributes make every field look like an empty one. What the
 * validation does with a form is covered in demosplan-ui, here it only has to let the send pass.
 */
vi.mock('@demos-europe/demosplan-ui', async importOriginal => ({
  ...(await importOriginal()),
  dpRpc: (...args) => mockDpRpc(...args),
  useDpValidate: () => ({ validate: () => true }),
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
  let store

  const openFormFor = async (segmentId, externId) => {
    store.commit('SegmentSlidebar/setContent', {
      prop: 'slidebar',
      val: { externId, isOpen: true, segmentId, showTab: 'sendViaMail' },
    })

    await nextTick()
  }

  /*
   * Children are stubbed by shallowMount, so fields are addressed as components and their
   * v-model is driven by emitting the event the parent listens to. In vue-2-compat mode
   * `v-model` compiles to `value` + `input`, and DpCheckbox declares `checked` + `change`.
   */
  const findField = id => wrapper.findComponent(`#${id}`)

  const setField = async (id, value) => {
    findField(id).vm.$emit('input', value)

    await nextTick()
  }

  const setCheckbox = async (id, value) => {
    findField(id).vm.$emit('change', value)

    await nextTick()
  }

  beforeEach(() => {
    mockDpRpc.mockClear()

    // `dplan.notify` is used by the mandatory-field branch of the form validation.
    global.dplan = { notify: { notify: vi.fn() } }

    store = createStore({
      modules: {
        SegmentSlidebar: {
          namespaced: true,
          state: () => ({
            slidebar: { externId: '', isOpen: false, segmentId: '', showTab: '' },
          }),
          mutations: {
            setContent (state, data) {
              state[data.prop] = data.val
            },
          },
        },
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

  it('stays hidden until the store opens the slidebar on this tab', async () => {
    expect(wrapper.find('#recipient').exists()).toBe(false)

    await openFormFor('segment-1', 'M7-2')

    expect(wrapper.find('#recipient').exists()).toBe(true)
  })

  it('prefills the reply-to address with the address of the current user', async () => {
    await openFormFor('segment-1', 'M7-2')

    expect(findField('replyToEmail').attributes('value')).toBe('planner@example.org')
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
    await setCheckbox('attachRecommendation', true)

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
    await setCheckbox('attachSegmentText', false)

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
