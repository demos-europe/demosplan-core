/**
 * Tests for the `applySelectionChange` guard that keeps the segmentation editor from crashing
 * when the segment being edited has no active range selection in the document (e.g. a segment
 * whose mark is missing from the textual reference, or a metadata-only edit). In that case the
 * command must stop the range edit cleanly and report success so the caller can persist the
 * segment metadata instead of throwing `TypeError: marks.selection is undefined`.
 */

/*
 * `applySelectionChange` derives the current range selection from the document via `getMarks`.
 * Mock the utilities module so each test controls whether a selection exists.
 */
jest.mock('@DpJs/lib/prosemirror/utilities', () => ({
  flattenNode: jest.fn(() => []),
  getMarks: jest.fn(),
  splitsExistingRange: jest.fn(() => false),
}))

import { applySelectionChange } from '@DpJs/lib/prosemirror/commands'
import { getMarks } from '@DpJs/lib/prosemirror/utilities'

/**
 * Builds a minimal ProseMirror-like view. `state.tr` is a chainable stub that records the meta
 * set on it, so we can assert the transaction that gets dispatched.
 */
const buildView = () => {
  const dispatchedMeta = []
  const tr = {
    setMeta (key, value) {
      dispatchedMeta.push({ key, value })

      return tr
    },
  }
  const state = { doc: {}, tr }
  const dispatch = jest.fn()

  return { view: { state, dispatch }, dispatch, tr, dispatchedMeta }
}

const editStateTrackerKey = { getState: () => ({ id: 'segment-without-range' }) }
// The edited segment has no tracked range (its mark is absent from the document).
const rangeTrackerKey = { getState: () => ({}) }

describe('applySelectionChange', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    // `dplan.notify` / `Translator` are used by the "segment too short" branch.
    global.dplan = { notify: { notify: jest.fn() } }
    global.Translator = { trans: jest.fn(key => key) }
  })

  it('does not throw and returns true when there is no active range selection', () => {
    // No `rangeselection` mark in the document → getMarks yields an object without `.selection`.
    getMarks.mockReturnValue({})

    const { view } = buildView()

    let result

    expect(() => {
      result = applySelectionChange(view, editStateTrackerKey, rangeTrackerKey)
    }).not.toThrow()
    expect(result).toBe(true)
  })

  it('stops the range edit (dispatches a stop-editing transaction) when no selection exists', () => {
    getMarks.mockReturnValue({})

    const { view, dispatch, dispatchedMeta } = buildView()

    applySelectionChange(view, editStateTrackerKey, rangeTrackerKey)

    expect(dispatch).toHaveBeenCalledTimes(1)
    // The dispatched transaction carries the editStateTracker "stop-editing" meta.
    expect(dispatchedMeta).toContainEqual({ key: editStateTrackerKey, value: 'stop-editing' })
  })

  it('does not persist a boundary change (returns false) for a genuine but too-short selection', () => {
    /*
     * A real selection shorter than the minimum segment length must be rejected — proving the
     * guard only short-circuits the missing-selection case and leaves normal validation intact.
     */
    getMarks.mockReturnValue({ selection: { from: 0, to: 5 } })

    const { view } = buildView()

    const result = applySelectionChange(view, editStateTrackerKey, rangeTrackerKey)

    expect(result).toBe(false)
    expect(global.dplan.notify.notify).toHaveBeenCalledWith('warning', 'warning.segment.too_short')
  })
})
