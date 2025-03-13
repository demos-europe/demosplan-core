/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { DOMSerializer } from 'prosemirror-model'
import { setRange } from './commands'
import { TextSelection } from 'prosemirror-state'
import tippy from 'tippy.js'

/**
 * Used to flatten a tree of prosemirror nodes.
 *
 * @param {prosemirror-node} node
 * @param {boolean} descend
 * @returns {Array}
 *
 */
function flattenNode (node, descend = true) {
  if (!node) {
    throw new Error('Invalid "node" parameter')
  }
  const result = []
  node.descendants((child, pos) => {
    result.push({ node: child, pos })
    if (!descend) {
      return false
    }
  })
  return result
}

/**
 * Used to extract mark positions of marks with a common attribute.
 * It takes a flattened prosemirror-node list including node positions and returns
 * an Object of mark positions { from, to } with the same attribute.
 *
 * @param {Array} nodes
 * @param {String} markName
 * @param {String} attrId
 * @returns {Object}
 *
 */
const getMarks = (nodes, markName, attrId) => {
  const marks = {}

  nodes.forEach(({ node, pos }) => {
    node.marks
      .filter(mark => mark.type.name === markName)
      .forEach(mark => {
        const markAttr = mark.attrs[attrId]
        let storedMark = marks[markAttr]

        if (!storedMark) {
          storedMark = { marks: [] }
          storedMark.rangeId = markAttr
          storedMark.from = pos
          marks[markAttr] = storedMark
        }

        storedMark.to = pos + node.nodeSize
        storedMark.isConfirmed = mark.attrs.isConfirmed
        storedMark.marks = [...storedMark.marks, { from: pos, to: storedMark.to }]
      })
  })

  return marks
}

/**
 * Checks if two range objects are equal ({ <id>: { from, to, isConfirmed, marks }, <id>: { from, to, isConfirmed, marks }}):
 * - Have ranges (rangeIds) been added or removed?
 * - Have range lengths (from, to) changed?
 * - Has the confirmation state (isConfirmed) changed?
 *
 * @param {Object} ranges ranges before update
 * @param {Object} cmpRanges ranges after update
 * @returns {Boolean}
 *
 */
const rangesEqual = (ranges, cmpRanges) => {
  // Check if there are any rangeIds in ranges that are not in cmpRanges (= were ranges deleted?)
  const keysEqual = Object.keys(ranges).filter(key => cmpRanges[key]).length === Object.keys(ranges).length
  if (!keysEqual) {
    return false
  }

  // Check if there are any rangeIds in cmpRanges that are not in ranges (= were ranges added?)
  const cmpKeysEqual = Object.keys(cmpRanges).filter(key => ranges[key]).length === Object.keys(cmpRanges).length
  if (!cmpKeysEqual) {
    return false
  }

  // Check if to, from or isConfirmed have changed
  const attributesEqual = Object.entries(ranges).filter(([key, val]) => {
    const cmpRange = cmpRanges[key]
    return cmpRange.from === val.from && cmpRange.to === val.to && cmpRange.isConfirmed === val.isConfirmed
  }).length === Object.keys(ranges).length
  if (!attributesEqual) {
    return false
  }

  return true
}

/**
 * Used to extract HTML content of a prosemirror fragment.
 *
 * @param {prosemirror-fragment} fragment
 * @param {prosemirror-schema} schema
 * @returns {String}
 *
 */
const serializeFragment = (fragment, schema) => {
  const container = document.createElement('div')
  return DOMSerializer.fromSchema(schema).serializeFragment(fragment, { document: window.document }, container)
}

/**
 * Extracts the HTML content of a from - to range ({ from: <int>, to: <int> }) from the given prosemirror state.
 *
 * @param {Object} range
 * @param {prosemirror-state} state
 * @param {prosemirror-schema} schema
 * @returns {String}
 *
 */
const serializeRange = (range, state, schema) => {
  const { doc } = state
  const { content } = doc.slice(range.from, range.to)
  return serializeFragment(content, schema).innerHTML
}

/**
 * Checks if a potential range between the passed in positions would split an existing range in two parts.
 *
 * @param {Number} from
 * @param {Number} to
 * @param {prosemirror-node} doc
 * @returns {Boolean}
 *
 */
const splitsExistingRange = (from, to, doc) => {
  const existingMarks = getMarks(flattenNode(doc), 'range', 'rangeId')
  const doesSplit = Object.values(existingMarks).filter((mark) => from > mark.from && to < mark.to)
  return doesSplit.length !== 0
}

/**
 * Takes to integers and returns an object with keys `from` and `to`. Key `from` will hold the lower and `to` will hold
 * the higher integer.
 *
 * @param {Number} num1
 * @param {Number} num2
 * @return {{from: number, to: number}}
 *
 */
const getMinMax = (num1, num2) => {
  const from = Math.min(num1, num2)
  const to = Math.max(num1, num2)
  return { from, to }
}

const range = (start, end, step = 1) => {
  const output = []
  if (typeof end === 'undefined') {
    end = start
    start = 0
  }
  for (let i = start; i < end; i += step) {
    output.push(i)
  }
  return output
}

const isSuperset = (set, subset) => {
  for (const elem of subset) {
    if (!set.has(elem)) {
      return false
    }
  }
  return true
}

/**
 * This creates a tippy tooltip menu which can be used to create a new range.
 *
 * @param {prosemirror-view} view
 * @param {Number} anchor
 * @param {Number} head
 * @returns {tippy-instance}
 *
 */
const createCreatorMenu = (view, anchor, head) => {
  const wrapper = document.createElement('div')
  wrapper.setAttribute('class', 'editor-menububble__wrapper is-active')

  const addBtn = document.createElement('button')
  addBtn.setAttribute('class', 'editor-menububble__button')
  addBtn.setAttribute('data-cy', 'menuBubbleButton')
  const icon = document.createElement('i')
  icon.setAttribute('class', 'fa fa-plus')
  addBtn.appendChild(icon)

  wrapper.addEventListener('click', (e) => {
    e.preventDefault()
    const from = Math.min(anchor, head)
    const to = Math.max(anchor, head)
    setRange(view)(from, to, { rangeId: `${from}_${to}` })
    const { state, dispatch } = view
    let { tr } = state
    view.focus()
    tr = tr.setSelection(new TextSelection(state.doc.resolve(to)))
    dispatch(tr)

    // Reset window/document selection to remove ::selection styling
    if (window.getSelection) {
      window.getSelection().removeAllRanges()
    } else if (document.selection) {
      document.selection.empty()
    }

    // Remove tippy
    if (bubbleMenu) {
      bubbleMenu.destroy()
    }

    // Matomo Tracking Event Tagging & Slicing
    if (window._paq) {
      window._paq.push(['trackEvent', 'ST Slicing Tagging', 'Click', Translator.trans('tag.new')])
    }
  })

  const bubbleMenu = tippy(view.dom, {
    duration: 0,
    arrow: false,
    theme: 'light-border',
    getReferenceClientRect: () => {
      const positions = view.coordsAtPos(head, -1)
      return {
        height: 10,
        width: 0,
        ...positions
      }
    },
    content: wrapper,
    interactive: true,
    trigger: 'manual',
    showOnCreate: true
  })

  wrapper.appendChild(addBtn)

  return bubbleMenu
}

const generateRangeChangeMap = (oldRanges, newRanges) => {
  const deletedRanges = Object.keys(oldRanges)
    .filter(key => !newRanges[key])
    .map(key => oldRanges[key])

  const createdRanges = Object.keys(newRanges)
    .filter(key => !oldRanges[key])
    .map(key => newRanges[key])

  const updatedRanges = Object.keys(oldRanges)
    .filter(key => {
      if (!newRanges[key]) {
        return false
      }
      const originalRange = oldRanges[key]
      const rangeToCompare = newRanges[key]
      const hasChangedPositions = originalRange.from !== rangeToCompare.from || originalRange.to !== rangeToCompare.to
      const hasChangedStatus = originalRange.isConfirmed !== rangeToCompare.isConfirmed
      const hasChangedText = originalRange.txt !== rangeToCompare.txt

      return hasChangedPositions || hasChangedStatus || hasChangedText
    })
    .map(key => newRanges[key])

  return {
    oldRanges,
    newRanges,
    deletedRanges,
    createdRanges,
    updatedRanges
  }
}

export {
  flattenNode,
  getMarks,
  getMinMax,
  rangesEqual,
  splitsExistingRange,
  serializeRange,
  range,
  isSuperset,
  createCreatorMenu,
  generateRangeChangeMap
}
