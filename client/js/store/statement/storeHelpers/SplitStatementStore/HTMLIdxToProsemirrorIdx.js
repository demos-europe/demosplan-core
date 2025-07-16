/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

function recalculateIndexPositions (segmentPositions, segmentPositionChanges, matches, offset) {
  const updatedSegmentPositionChanges = [...segmentPositionChanges]
  matches.forEach(match => {
    const fullMatch = match[0]
    const matchLength = fullMatch.length
    const matchStart = match.index
    const matchEnd = matchStart + matchLength
    segmentPositions.forEach((segmentPosition, idx) => {
      if (matchEnd <= segmentPosition) {
        // We subtract the length of the matched tag and add 1 because this is a prosemirror node.
        updatedSegmentPositionChanges[idx] = updatedSegmentPositionChanges[idx] - matchLength + offset
      }
    })
  })
  return updatedSegmentPositionChanges
}

/**
 * This function transforms HTLM character positions to prosemirror indices. It differentiates between so called
 * marks and nodes. While entering or leaving a node in prosemirror counts as one index position, entering or leaving a
 * mark does not count as one index position. It also accounts for HTML entities (e.g. &nbsp;) which are also count as
 * one index position in prosemirror.
 *
 * Read more about prosemirror indexing at: https://prosemirror.net/docs/guide/#indexing
 *
 * You may realize that this is parsing HTML using regular expressions. You may be aware of this famous StackOverflow
 * comment https://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454 .
 * Please note, that this is only parsing a very limited subset of HTML:
 * https://stackoverflow.com/a/1733489
 *
 * Also, I did not find a way to access the actual character length of a DOM-Node using DOM-based-methods.
 *
 * See HTMLIdxToProsemirrorIdx.spec.js for a better idea on how this is supposed to work.
 *
 * @param {Array} segments
 * @param {String} initialText
 * @param {Array|null} allowedNodes
 * @param {Array|null} allowedMarks
 * @return {Array}
 */
function transformHTMLPositionsToProsemirrorPositions (segments, initialText, allowedNodes = null, allowedMarks = null) {
  const PROSEMIRROR_NODE_SIZE = 1
  const PROSEMIRROR_MARK_SIZE = 0

  const allowedNodeTags = allowedNodes || ['p', 'div', 'img', 'ol', 'ul', 'li', 'br']
  const nodeGroup = allowedNodeTags.join('|')
  const nodeRegex = new RegExp(`</?(${nodeGroup}).*?>`, 'ig')
  const allowedMarkTags = allowedMarks || ['strong', 'span', 'a', 'b', 'em', 'i', 'del', 'ins']
  const markGroup = allowedMarkTags.join('|')
  const markRegex = new RegExp(`</?(?!br)(${markGroup}).*?>`, 'ig')
  const entitiesRegex = /&(#[0-9]+|[a-z]+);/g

  // We construct a flat array of positions to easily run calculations later.
  const segmentPositions = []
  segments.forEach(segment => {
    segmentPositions.push(segment.charStart)
    segmentPositions.push(segment.charEnd)
  })

  /*
   * We initialize an array of zeros of the same length as the segment position array.
   * This array will track position changes.
   * We can't apply position changes immediately as this would only work for the first change made.
   */
  let segmentPositionChanges = new Array(segmentPositions.length).fill(0)

  const nodeMatches = [...initialText.matchAll(nodeRegex)]
  const entityMatches = [...initialText.matchAll(entitiesRegex)]
  const sizeOneMatches = nodeMatches.concat(entityMatches)
  segmentPositionChanges = recalculateIndexPositions(segmentPositions, segmentPositionChanges, sizeOneMatches, PROSEMIRROR_NODE_SIZE)

  const markMatches = [...initialText.matchAll(markRegex)]
  segmentPositionChanges = recalculateIndexPositions(segmentPositions, segmentPositionChanges, markMatches, PROSEMIRROR_MARK_SIZE)

  // We now apply all position changes at once.
  const changedSegmentPositions = segmentPositions.map((position, idx) => position + segmentPositionChanges[idx])

  const repositionedSegments = segments.map(segment => {
    /*
     * Do not set character positions again which were already recalculated.
     * This would lead to shifted positions as the HTML to Prosemirror transformation would be done more than once.
     */
    if (!segment.charStartInit) {
      segment.charStartInit = segment.charStart
      // When using shift() the first 2 elements of changedSegmentPositions will refer to a segment's charStart and charEnd.
      segment.charStart = changedSegmentPositions.shift()
      segment.charEndInit = segment.charEnd
      segment.charEnd = changedSegmentPositions.shift()
    }
    return segment
  })

  return repositionedSegments
}

export { transformHTMLPositionsToProsemirrorPositions }
