/**
 * Tests for the range-less-segment guards in SplitStatementView's enter/leave edit-mode methods.
 * A segment whose mark is missing from the document has no range in the range tracker; the range
 * machinery (`setRangeEditingState`, `activateRangeEdit`) must be skipped for it so that its
 * metadata can still be edited, while normal (marked) segments keep the full range-editing flow.
 */

// Mock the prosemirror commands so we can assert whether the range machinery is invoked.
jest.mock('@DpJs/lib/prosemirror/commands', () => ({
  activateRangeEdit: jest.fn(),
  applySelectionChange: jest.fn(),
  removeRange: jest.fn(),
  setRange: jest.fn(),
  // `setRangeEditingState` is curried: setRangeEditingState(view, ...)(id, state).
  setRangeEditingState: jest.fn(() => jest.fn()),
}))

import { activateRangeEdit, setRangeEditingState } from '@DpJs/lib/prosemirror/commands'
import SplitStatementView from '@DpJs/components/statement/splitStatement/SplitStatementView'

const { enableEditMode, disableEditMode } = SplitStatementView.methods

/**
 * Builds a mock component context. `ranges` maps segmentId → range object; a segment absent from
 * it has no range in the document (i.e. its mark is missing).
 */
const buildContext = (ranges = {}) => ({
  editModeActive: false,
  editingSegment: null,
  ignoreProsemirrorUpdates: false,
  stateBeforeEditing: null,
  prosemirror: {
    view: { state: {} },
    keyAccess: {
      rangeTrackerKey: { getState: () => ranges },
      editingDecorationsKey: {},
      editStateTrackerKey: {},
    },
  },
  segmentById: id => ({ id, tags: [] }),
  setProperty: jest.fn(),
})

describe('SplitStatementView edit-mode range-less guards', () => {
  beforeEach(() => jest.clearAllMocks())

  describe('enableEditMode', () => {
    it('opens the editor but skips range activation for a segment without a range', () => {
      const context = buildContext({}) // No ranges → segment has no mark

      expect(() => enableEditMode.call(context, 'segment-without-range')).not.toThrow()

      // The metadata editor is opened (segment selected, edit mode on) ...
      expect(context.setProperty).toHaveBeenCalledWith({ prop: 'editingSegment', val: { id: 'segment-without-range', tags: [] } })
      expect(context.setProperty).toHaveBeenCalledWith({ prop: 'editModeActive', val: true })
      // ... but the range machinery is not touched.
      expect(setRangeEditingState).not.toHaveBeenCalled()
      expect(activateRangeEdit).not.toHaveBeenCalled()
    })

    it('activates range editing for a segment that has a range', () => {
      const context = buildContext({ 'segment-with-range': { from: 1, to: 20, isConfirmed: true } })

      enableEditMode.call(context, 'segment-with-range')

      expect(setRangeEditingState).toHaveBeenCalled()
      expect(activateRangeEdit).toHaveBeenCalled()
    })
  })

  describe('disableEditMode', () => {
    it('skips range reset for an edited segment without a range', () => {
      const context = buildContext({})

      context.editingSegment = { id: 'segment-without-range' }

      expect(() => disableEditMode.call(context)).not.toThrow()

      expect(setRangeEditingState).not.toHaveBeenCalled()
      // Edit state is still cleared.
      expect(context.setProperty).toHaveBeenCalledWith({ prop: 'editingSegment', val: null })
      expect(context.setProperty).toHaveBeenCalledWith({ prop: 'editModeActive', val: false })
    })

    it('resets range editing for an edited segment that has a range', () => {
      const context = buildContext({ 'segment-with-range': { from: 1, to: 20, isConfirmed: true } })

      context.editingSegment = { id: 'segment-with-range' }

      disableEditMode.call(context)

      expect(setRangeEditingState).toHaveBeenCalled()
      expect(context.setProperty).toHaveBeenCalledWith({ prop: 'editingSegment', val: null })
    })
  })
})
