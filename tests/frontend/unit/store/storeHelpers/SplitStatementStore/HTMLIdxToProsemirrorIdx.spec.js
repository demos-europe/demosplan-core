/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { transformHTMLPositionsToProsemirrorPositions } from '@DpJs/store/statement/storeHelpers/SplitStatementStore/HTMLIdxToProsemirrorIdx'

describe('It should transform html char references to prosemirror indices.', () => {
  it('should subtract the length of html tags from char positions', () => {
    const initText = '<p>12345</p><ul><li>6</li></ul>'
    const segments = [
      { charStart: 3, charEnd: 8 },
      { charStart: 20, charEnd: 21 }
    ]

    const transformedSegments = transformHTMLPositionsToProsemirrorPositions(segments, initText)
    expect(transformedSegments[0].charStart).toBe(1)
    expect(transformedSegments[0].charEnd).toBe(6)
    expect(transformedSegments[1].charStart).toBe(9)
    expect(transformedSegments[1].charEnd).toBe(10)
  })

  it('should subtract html chars but not add one for tags which represent prosemirror marks', () => {
    const initText = '<a href="#">12345</a><ul><li>6</li></ul>'
    const segments = [
      { charStart: 12, charEnd: 17 },
      { charStart: 29, charEnd: 30 }
    ]

    const transformedSegments = transformHTMLPositionsToProsemirrorPositions(segments, initText)
    expect(transformedSegments[0].charStart).toBe(0)
    expect(transformedSegments[0].charEnd).toBe(5)
    expect(transformedSegments[1].charStart).toBe(7)
    expect(transformedSegments[1].charEnd).toBe(8)
  })

  it('should subtract html entities and add one for each entity', () => {
    const initText = '0123&nbsp;56&#181;89'
    const segments = [
      { charStart: 0, charEnd: 10 },
      { charStart: 10, charEnd: 20 }
    ]

    const transformedSegments = transformHTMLPositionsToProsemirrorPositions(segments, initText)
    expect(transformedSegments[0].charStart).toBe(0)
    expect(transformedSegments[0].charEnd).toBe(5)
    expect(transformedSegments[1].charStart).toBe(5)
    expect(transformedSegments[1].charEnd).toBe(10)
  })

  it('should only parse allowed nodes when given', () => {
    const initText = '<p>12345</p><ul><li>6</li></ul>'
    const segments = [
      { charStart: 3, charEnd: 8 },
      { charStart: 20, charEnd: 21 }
    ]

    const transformedSegments = transformHTMLPositionsToProsemirrorPositions(segments, initText, ['div'])
    expect(transformedSegments[0].charStart).toBe(3)
    expect(transformedSegments[0].charEnd).toBe(8)
    expect(transformedSegments[1].charStart).toBe(20)
    expect(transformedSegments[1].charEnd).toBe(21)
  })

  it('should only parse allowed marks when given', () => {
    const initText = '<em>12345</em><ul><li>6</li></ul>'
    const segments = [
      { charStart: 4, charEnd: 9 },
      { charStart: 21, charEnd: 22 }
    ]

    const transformedSegments = transformHTMLPositionsToProsemirrorPositions(segments, initText, ['div'], ['strong'])
    expect(transformedSegments[0].charStart).toBe(4)
    expect(transformedSegments[0].charEnd).toBe(9)
    expect(transformedSegments[1].charStart).toBe(21)
    expect(transformedSegments[1].charEnd).toBe(22)
  })
})
