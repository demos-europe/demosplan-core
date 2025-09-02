/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This mark is used to represent range shaped information.
 */
const segmentMark = {
  attrs: {
    isActive: { default: false },
    isMoving: { default: false },
    isConfirmed: { default: false },
    pmId: { default: null },
    segmentId: { default: null },
  },
  inclusive: true,
  parseDOM: [{
    tag: 'segment-mark',
    getAttrs (dom) {
      return {
        isActive: dom.getAttribute('data-range-active'),
        isMoving: dom.getAttribute('data-range-moving'),
        isConfirmed: dom.getAttribute('data-range-confirmed'),
        pmId: dom.getAttribute('data-pm-id'),
        segmentId: dom.getAttribute('data-segment-id'),
      }
    },
  }],
  toDOM (node) {
    const { isActive, isConfirmed, isMoving, pmId, segmentId } = node.attrs
    return ['span', {
      'data-range-active': isActive,
      'data-range-moving': isMoving,
      'data-range-confirmed': isConfirmed,
      'data-pm-id': pmId,
      'data-segment-id': segmentId,
    }, 0]
  },
}

/**
 * This mark is used to represent a range currently being changed.
 */
const rangeSelectionMark = {
  attrs: {
    active: { default: null },
    pmId: { default: null },
    rangeType: { default: 'selection' },
  },
  inclusive: true,
  parseDOM: [{
    tag: 'span[data-range-selected]',
    getAttrs (dom) {
      return { active: dom.getAttribute('data-range-selected'), pmId: dom.getAttribute('data-pm-id'), rangeType: dom.getAttribute('data-range-type') }
    },
  }],
  toDOM (node) {
    const { active, pmId } = node.attrs
    return ['span', { 'data-range-selected': active, 'data-pm-id': pmId, 'data-range-type': 'selection' }, 0]
  },
}

export { segmentMark, rangeSelectionMark }
