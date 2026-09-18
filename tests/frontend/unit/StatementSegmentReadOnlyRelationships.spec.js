/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import StatementSegment from '@DpJs/components/procedure/StatementSegmentsList/StatementSegment'

/*
 * `comments` and `recommendationVersions` are read-only on the StatementSegment resource. The BE rejects
 * the whole PATCH when either reaches the update payload, so they must never be written to the store -
 * that is what the vuex-json-api save action diffs against to build the request body.
 */
describe('StatementSegment read-only relationships', () => {
  const comments = { data: [{ id: 'comment-id', type: 'SegmentComment' }] }
  const recommendationVersions = { data: [{ id: 'version-id', type: 'RecommendationVersion' }] }

  const createContext = (overrides = {}) => ({
    segment: {
      id: 'segment-id',
      attributes: {},
      relationships: {
        assignee: { data: { id: 'other-user-id', type: 'AssignableUser' } },
        comments,
        parentStatement: { data: { id: 'statement-id', type: 'Statement' } },
        recommendationVersions,
      },
    },
    segmentItems: {},
    selectedAssignee: { id: 'user-id' },
    selectedPlace: { id: 'place-id', type: 'Place' },
    setSegment: vi.fn(),
    showWorkflowFields: true,
    ...overrides,
  })

  describe('updateRelationships', () => {
    it('keeps read-only relationships out of the item written to the store', () => {
      const context = createContext()

      StatementSegment.methods.updateRelationships.call(context)

      const [stored] = context.setSegment.mock.calls[0]

      expect(stored.relationships).not.toHaveProperty('comments')
      expect(stored.relationships).not.toHaveProperty('recommendationVersions')
    })

    it('keeps the updatable relationships', () => {
      const context = createContext()

      StatementSegment.methods.updateRelationships.call(context)

      const [stored] = context.setSegment.mock.calls[0]

      expect(stored.relationships.parentStatement.data.id).toBe('statement-id')
      expect(stored.relationships.assignee.data.id).toBe('user-id')
      expect(stored.relationships.place.data.id).toBe('place-id')
    })

    it('strips read-only relationships even when the workflow fields are hidden', () => {
      const context = createContext({ showWorkflowFields: false })

      StatementSegment.methods.updateRelationships.call(context)

      const [stored] = context.setSegment.mock.calls[0]

      expect(stored.relationships).not.toHaveProperty('comments')
      expect(stored.relationships).not.toHaveProperty('recommendationVersions')
    })
  })

  describe('restoreReadOnlyRelationships', () => {
    const createStoredContext = (relationships) => createContext({
      segmentItems: {
        'segment-id': { id: 'segment-id', attributes: {}, relationships },
      },
    })

    it('re-adds the stripped relationships so comments stay visible while saving', () => {
      const context = createStoredContext({ assignee: { data: null } })

      StatementSegment.methods.restoreReadOnlyRelationships.call(context, { comments, recommendationVersions })

      const [stored] = context.setSegment.mock.calls[0]

      expect(stored.relationships.comments).toStrictEqual(comments)
      expect(stored.relationships.recommendationVersions).toStrictEqual(recommendationVersions)
      expect(stored.relationships.assignee).toStrictEqual({ data: null })
    })

    it('does not overwrite relationships the store already holds', () => {
      const freshComments = { data: [] }
      const context = createStoredContext({ comments: freshComments })

      StatementSegment.methods.restoreReadOnlyRelationships.call(context, { comments, recommendationVersions })

      const [stored] = context.setSegment.mock.calls[0]

      expect(stored.relationships.comments).toStrictEqual(freshComments)
      expect(stored.relationships.recommendationVersions).toStrictEqual(recommendationVersions)
    })

    it('writes nothing when there is nothing to restore', () => {
      const context = createStoredContext({ comments, recommendationVersions })

      StatementSegment.methods.restoreReadOnlyRelationships.call(context, { comments, recommendationVersions })

      expect(context.setSegment).not.toHaveBeenCalled()
    })

    it('writes nothing when the segment is not in the store', () => {
      const context = createContext()

      StatementSegment.methods.restoreReadOnlyRelationships.call(context, { comments, recommendationVersions })

      expect(context.setSegment).not.toHaveBeenCalled()
    })
  })
})
