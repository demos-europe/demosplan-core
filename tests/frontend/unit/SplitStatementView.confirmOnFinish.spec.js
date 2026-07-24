/**
 * Tests for auto-confirming unconfirmed proposals when finishing a split.
 * Clicking "Aufteilen abschließen" must confirm every remaining proposal so it is persisted in a
 * valid, confirmed state (segmentMark gets a pmId, store status becomes 'confirmed') instead of
 * being saved as a broken, never-confirmed segment.
 */

/*
 * Mock the prosemirror commands so we can assert whether/how the range machinery is invoked.
 * `setRange` is curried: setRange(view)(from, to, attrs).
 */
import { afterAll, beforeAll, beforeEach, describe, expect, it, jest } from '@jest/globals'

const mockSetRangeInner = jest.fn()

jest.mock('@DpJs/lib/prosemirror/commands', () => ({
  activateRangeEdit: jest.fn(),
  applySelectionChange: jest.fn(),
  removeRange: jest.fn(),
  setRange: jest.fn(() => mockSetRangeInner),
  setRangeEditingState: jest.fn(() => jest.fn()),
}))

import { setRange } from '@DpJs/lib/prosemirror/commands'
import SplitStatementView from '@DpJs/components/statement/splitStatement/SplitStatementView'

const { confirmAllUnconfirmedSegments, saveAndFinish, clickTrackerSaveButton } = SplitStatementView.methods

/**
 * Builds a mock component context. `segments` is the store segment list; `ranges` maps
 * segmentId → range object (a segment absent from it has no mark in the document).
 */
const buildContext = (segments, ranges) => ({
  segments,
  ignoreProsemirrorUpdates: false,
  prosemirror: {
    view: { state: {} },
    keyAccess: {
      rangeTrackerKey: { getState: () => ranges },
    },
  },
  locallyUpdateSegments: jest.fn(),
})

describe('SplitStatementView.confirmAllUnconfirmedSegments', () => {
  beforeEach(() => jest.clearAllMocks())

  it('confirms only the unconfirmed segments and marks them confirmed in the store', () => {
    const segments = [
      { id: 'a', status: 'confirmed', tags: [] },
      { id: 'b', status: false, tags: [] },
      { id: 'c', status: false, tags: [] },
    ]
    const ranges = {
      a: { from: 1, to: 10, isConfirmed: true },
      b: { from: 11, to: 20, isConfirmed: false },
      c: { from: 21, to: 30, isConfirmed: false },
    }
    const context = buildContext(segments, ranges)

    confirmAllUnconfirmedSegments.call(context)

    // The already-confirmed segment is left untouched; only the two proposals are confirmed.
    expect(mockSetRangeInner).toHaveBeenCalledTimes(2)
    expect(mockSetRangeInner).toHaveBeenCalledWith(11, 20, { segmentId: 'b', isConfirmed: true })
    expect(mockSetRangeInner).toHaveBeenCalledWith(21, 30, { segmentId: 'c', isConfirmed: true })

    // The store is updated with the newly confirmed segments.
    expect(context.locallyUpdateSegments).toHaveBeenCalledWith([
      { id: 'b', status: 'confirmed', tags: [] },
      { id: 'c', status: 'confirmed', tags: [] },
    ])
  })

  it('does nothing when every segment is already confirmed', () => {
    const segments = [
      { id: 'a', status: 'confirmed', tags: [] },
      { id: 'b', status: 'confirmed', tags: [] },
    ]
    const ranges = {
      a: { from: 1, to: 10, isConfirmed: true },
      b: { from: 11, to: 20, isConfirmed: true },
    }
    const context = buildContext(segments, ranges)

    confirmAllUnconfirmedSegments.call(context)

    expect(setRange).not.toHaveBeenCalled()
    expect(context.locallyUpdateSegments).not.toHaveBeenCalled()
  })

  it('skips an unconfirmed segment that has no range without throwing', () => {
    const segments = [
      { id: 'a', status: false, tags: [] },
      { id: 'b', status: false, tags: [] },
    ]
    // Segment 'a' has no mark in the document.
    const ranges = {
      b: { from: 11, to: 20, isConfirmed: false },
    }
    const context = buildContext(segments, ranges)

    expect(() => confirmAllUnconfirmedSegments.call(context)).not.toThrow()

    // Only the segment with a range is confirmed.
    expect(mockSetRangeInner).toHaveBeenCalledTimes(1)
    expect(mockSetRangeInner).toHaveBeenCalledWith(11, 20, { segmentId: 'b', isConfirmed: true })
    expect(context.locallyUpdateSegments).toHaveBeenCalledWith([
      { id: 'b', status: 'confirmed', tags: [] },
    ])
  })
})

/**
 * Builds a saveAndFinish context wired to a backing store state so the send step sees the effects of
 * confirmAllUnconfirmedSegments. `getPostedSegments` returns a snapshot of exactly what saveSegmentsFinal
 * would have sent to the backend, captured at the moment it is invoked.
 */
const buildFinishContext = (initialSegments, ranges) => {
  // Backing store state; `segments` reads from it so store mutations are visible to the send step.
  const state = { segments: initialSegments.map(segment => ({ ...segment })) }
  let postedSegments = null

  const context = {
    get segments () {
      return state.segments
    },
    ignoreProsemirrorUpdates: false,
    prosemirror: {
      view: { state: {} },
      getContent: jest.fn(() => '<p>statement</p>'),
      keyAccess: {
        rangeTrackerKey: { getState: () => ranges },
      },
    },
    setProperty: jest.fn(),
    clickTrackerSaveButton,
    confirmAllUnconfirmedSegments,
    // Mirrors the SplitStatementStore locallyUpdateSegments mutation: merge updates into state by id.
    locallyUpdateSegments: jest.fn(updatedSegments => {
      state.segments = state.segments.map(segment => {
        const updated = updatedSegments.find(candidate => candidate.id === segment.id)

        return updated ? { ...segment, ...updated } : segment
      })
    }),
    // Stands in for the store action that POSTs to the backend; captures what would be sent.
    saveSegmentsFinal: jest.fn(function () {
      postedSegments = this.segments.map(segment => ({ ...segment }))

      return Promise.resolve(true)
    }),
  }

  return { context, getPostedSegments: () => postedSegments }
}

describe('SplitStatementView.saveAndFinish — backend payload', () => {
  let originalDpconfirm

  beforeAll(() => {
    originalDpconfirm = window.dpconfirm
    global.Translator = { trans: key => key }
    global.dplan = { notify: { error: jest.fn() } }
  })

  afterAll(() => {
    window.dpconfirm = originalDpconfirm
    delete global.Translator
    delete global.dplan
  })

  beforeEach(() => {
    jest.clearAllMocks()
    window.dpconfirm = jest.fn(() => true)
  })

  it('confirms every proposal before the segments are sent to the backend', async () => {
    /*
     * Mirrors the ticket bug: an AI/pipeline proposal that was created (so it has a segmentMark, and
     * therefore a range keyed by segmentId) but never confirmed — its statementText spans still lack a
     * pmId. Finishing must confirm it so it is never persisted in that broken state.
     */
    const segments = [
      { id: 'a', status: 'confirmed', tags: [] },
      { id: 'b', status: false, tags: [] },
      { id: 'c', status: false, tags: [] },
    ]
    const ranges = {
      a: { from: 1, to: 10, isConfirmed: true },
      b: { from: 11, to: 20, isConfirmed: false },
      c: { from: 21, to: 30, isConfirmed: false },
    }
    const { context, getPostedSegments } = buildFinishContext(segments, ranges)

    await saveAndFinish.call(context)

    // Each proposal is confirmed in the document (setRange assigns the pmId) before sending.
    expect(mockSetRangeInner).toHaveBeenCalledWith(11, 20, { segmentId: 'b', isConfirmed: true })
    expect(mockSetRangeInner).toHaveBeenCalledWith(21, 30, { segmentId: 'c', isConfirmed: true })

    // The payload handed to the backend never contains an unconfirmed segment.
    expect(context.saveSegmentsFinal).toHaveBeenCalledTimes(1)
    const posted = getPostedSegments()

    expect(posted).toHaveLength(3)
    expect(posted.every(segment => segment.status === 'confirmed')).toBe(true)
  })

  it('does not send anything to the backend when the user cancels the confirm dialog', async () => {
    window.dpconfirm.mockReturnValueOnce(false)
    const { context } = buildFinishContext(
      [{ id: 'a', status: false, tags: [] }],
      { a: { from: 1, to: 10, isConfirmed: false } },
    )

    await saveAndFinish.call(context)

    expect(context.saveSegmentsFinal).not.toHaveBeenCalled()
  })
})
