/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import SplitStatementStore from '@DpJs/store/statement/SplitStatementStore'
import { vi } from 'vitest'

const { mockDpApiPatch, mockDpApiPost } = vi.hoisted(() => ({
  mockDpApiPatch: vi.fn(() => Promise.resolve()),
  mockDpApiPost: vi.fn(() => Promise.resolve({ data: { data: { nextStatementId: '' } } })),
}))

vi.mock('@demos-europe/demosplan-ui', async importOriginal => ({
  ...(await importOriginal()),
  dpApi: { patch: mockDpApiPatch, post: mockDpApiPost },
}))

describe('SplitStatement store', () => {
  describe('applyTagDefaultAssignees', () => {
    const buildTag = (id, defaultAssigneeId = null) => ({
      id,
      relationships: defaultAssigneeId ?
        { defaultAssignee: { data: { id: defaultAssigneeId } } } :
        {},
    })

    const runAction = ({ segments, availableTags = [], assignableUsers = [] }) => {
      const state = { segments, availableTags, assignableUsers }
      const commit = vi.fn()

      SplitStatementStore.actions.applyTagDefaultAssignees({ state, commit })

      return commit
    }

    beforeEach(() => {
      globalThis.hasPermission = vi.fn(() => true)
      globalThis.structuredClone = globalThis.structuredClone || (value => JSON.parse(JSON.stringify(value)))
    })

    it('does nothing when the feature permission is disabled', () => {
      globalThis.hasPermission = vi.fn(() => false)

      const commit = runAction({
        segments: [{ id: 'seg1', tags: [{ id: 'tag1' }] }],
        availableTags: [buildTag('tag1', 'user1')],
        assignableUsers: [{ id: 'user1' }],
      })

      expect(commit).not.toHaveBeenCalled()
    })

    it('assigns the default assignee of the first tag that has an assignable one', () => {
      const commit = runAction({
        segments: [{ id: 'seg1', tags: [{ id: 'tag1' }] }],
        availableTags: [buildTag('tag1', 'user1')],
        assignableUsers: [{ id: 'user1' }],
      })

      expect(commit).toHaveBeenCalledWith('setProperty', {
        prop: 'segments',
        val: [expect.objectContaining({ id: 'seg1', assigneeId: 'user1' })],
      })
    })

    it('lets the first tag with a default assignee win', () => {
      const commit = runAction({
        segments: [{ id: 'seg1', tags: [{ id: 'tag1' }, { id: 'tag2' }] }],
        availableTags: [buildTag('tag1', 'user1'), buildTag('tag2', 'user2')],
        assignableUsers: [{ id: 'user1' }, { id: 'user2' }],
      })

      const committedSegments = commit.mock.calls[0][1].val

      expect(committedSegments[0].assigneeId).toBe('user1')
    })

    it('does not overwrite an existing assignee', () => {
      const commit = runAction({
        segments: [{ id: 'seg1', assigneeId: 'manual', tags: [{ id: 'tag1' }] }],
        availableTags: [buildTag('tag1', 'user1')],
        assignableUsers: [{ id: 'user1' }],
      })

      const committedSegments = commit.mock.calls[0][1].val

      expect(committedSegments[0].assigneeId).toBe('manual')
    })

    it('skips a default assignee that is not assignable in the procedure', () => {
      const commit = runAction({
        segments: [{ id: 'seg1', tags: [{ id: 'tag1' }] }],
        availableTags: [buildTag('tag1', 'user1')],
        assignableUsers: [{ id: 'someoneElse' }],
      })

      const committedSegments = commit.mock.calls[0][1].val

      expect(committedSegments[0].assigneeId).toBeUndefined()
    })

    it('leaves a segment unassigned when no tag has a default assignee', () => {
      const commit = runAction({
        segments: [{ id: 'seg1', tags: [{ id: 'tag1' }] }],
        availableTags: [buildTag('tag1')],
        assignableUsers: [{ id: 'user1' }],
      })

      const committedSegments = commit.mock.calls[0][1].val

      expect(committedSegments[0].assigneeId).toBeUndefined()
    })
  })

  describe('saveSegmentsDrafts / saveSegmentsFinal — contentBlocks payload', () => {
    beforeEach(() => {
      globalThis.structuredClone = globalThis.structuredClone || (value => JSON.parse(JSON.stringify(value)))
      mockDpApiPatch.mockClear()
      mockDpApiPost.mockClear()
    })

    const buildState = () => ({
      statementId: 'statement-1',
      initialData: {
        id: 'statement-1',
        type: 'SegementedStatement',
        attributes: {
          statementId: 'statement-1',
          procedureId: 'procedure-1',
          textualReference: '<p>Old legacy HTML</p>',
          segments: [],
        },
      },
      initText: '<segment-mark data-segment-id="a">Text</segment-mark>',
      segments: [{ id: 'a', tags: [] }],
      initialSegments: [],
      contentBlocks: [{ type: 'segment', order: 1, id: 'a', text: 'Text', textRaw: 'Text', status: 'confirmed', tags: [] }],
    })

    it('saveSegmentsDrafts sends contentBlocks alongside the legacy fields', async () => {
      const state = buildState()
      const commit = vi.fn()

      await SplitStatementStore.actions.saveSegmentsDrafts({ state, commit, dispatch: vi.fn() }, false)

      // The PATCH body wraps the drafts-info payload inside attributes.segmentDraftList.data.
      const dataToSend = mockDpApiPatch.mock.calls[0][2].data.attributes.segmentDraftList.data

      expect(dataToSend.attributes.contentBlocks).toEqual(state.contentBlocks)
      /*
       * Legacy fields are still sent too (harmless once contentBlocks is present server-side) -
       * saveSegmentsDrafts' own success handler re-syncs initialData/initialSegments from them.
       */
      expect(dataToSend.attributes.textualReference).toBe(state.initText)
      expect(dataToSend.attributes.segments).toEqual(state.segments)
    })

    it('saveSegmentsFinal sends contentBlocks', async () => {
      const state = buildState()
      const commit = vi.fn()

      await SplitStatementStore.actions.saveSegmentsFinal({ state, commit, dispatch: vi.fn() })
        .catch(() => {}) // Navigation side effects aren't under test here

      const dataToSend = mockDpApiPost.mock.calls[0][2].data

      expect(dataToSend.attributes.contentBlocks).toEqual(state.contentBlocks)
    })
  })

  describe('fetchInitialData / setInitialData — format detection', () => {
    it('setInitialData seeds a legacy-shaped initialData and resets contentBlocks', async () => {
      const state = {
        statementId: 'statement-1',
        procedureId: 'procedure-1',
        statement: { attributes: { fullText: '<p>Fresh statement text</p>' } },
      }
      const commit = vi.fn((mutation, payload) => {
        if (mutation === 'setProperty') {
          state[payload.prop] = payload.val
        }
      })
      const dispatch = vi.fn(() => Promise.resolve())

      await SplitStatementStore.actions.setInitialData({ state, commit, dispatch })

      expect(state.initialData.attributes.textualReference).toBe('<p>Fresh statement text</p>')
      expect(state.initialData.attributes).not.toHaveProperty('contentBlocks')
      expect(state.contentBlocks).toEqual([])
    })
  })
})
