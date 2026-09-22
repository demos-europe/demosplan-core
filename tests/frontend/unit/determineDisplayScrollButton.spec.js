/**
 * Tests for the `determineDisplayScrollButton` guard in SplitStatementView. When the segment
 * currently being edited has no active span in the document (e.g. a segment whose mark is missing
 * from the textual reference), scrolling must not crash with
 * `TypeError: can't access property "getBoundingClientRect", lastSegmentSpan is undefined`.
 * The method must return false instead.
 */

import SplitStatementView from '@DpJs/components/statement/splitStatement/SplitStatementView'

const determineDisplayScrollButton = SplitStatementView.methods.determineDisplayScrollButton

describe('SplitStatementView.determineDisplayScrollButton', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('returns false and does not throw when no active segment span exists', () => {
    // No `span[data-range-active="true"]` in the document.
    document.body.innerHTML = '<div id="header"></div><p>Some unsegmented text</p>'

    const context = { scrollButtonPosition: null }

    let result

    expect(() => {
      result = determineDisplayScrollButton.call(context)
    }).not.toThrow()
    expect(result).toBe(false)
    // The scroll position must be left untouched when there is nothing to scroll to.
    expect(context.scrollButtonPosition).toBeNull()
  })
})
