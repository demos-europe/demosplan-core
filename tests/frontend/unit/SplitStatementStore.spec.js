/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import SplitStatementStore from '@DpJs/store/statement/SplitStatementStore'

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
      const commit = jest.fn()

      SplitStatementStore.actions.applyTagDefaultAssignees({ state, commit })

      return commit
    }

    beforeEach(() => {
      globalThis.hasPermission = jest.fn(() => true)
      globalThis.structuredClone = globalThis.structuredClone || (value => JSON.parse(JSON.stringify(value)))
    })

    it('does nothing when the feature permission is disabled', () => {
      globalThis.hasPermission = jest.fn(() => false)

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
})
