/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Decoration, DecorationSet } from 'prosemirror-view'
import { flattenNode, getMarks, splitsExistingRange } from './utilities'
import { TextSelection } from 'prosemirror-state'
import { v4 as uuidv4 } from 'uuid'

/**
 * @param {prosemirror-view} view
 * @param {prosemirror-pluginkey} editStateTrackerKey
 * @param {prosemirror-pluginkey} rangeTrackerKey
 */
const applySelectionChange = (view, editStateTrackerKey, rangeTrackerKey) => {
  const { state, dispatch } = view

  const rangeId = editStateTrackerKey.getState(state).id
  const range = rangeTrackerKey.getState(state)[rangeId]

  /**
   * We get the current rangeselection here so that we can use these new positions to apply them to the range
   * currently being edited
   */
  const nodes = flattenNode(state.doc)
  const marks = getMarks(nodes, 'rangeselection', 'rangeType')
  const { from, to } = marks.selection
  let tr = state.tr

  if (to - from < 10) {
    dplan.notify.notify('warning', Translator.trans('warning.segment.too_short'))
    dispatch(tr)

    return false
  }

  /**
   * This replaces the old range with a new range. It also removes any ranges which might now be covered by the new range.
   */
  tr = removeRange(state, range.from, range.to, tr)
  tr = replaceRange(state, from, to, { rangeId, isActive: true, isConfirmed: true }, tr)
  tr = disableRangeEdit(view, editStateTrackerKey, tr)

  dispatch(tr)

  return true
}

/**
 * Used to replace a range at the specified positions.
 *
 * @param {prosemirror-state} state
 * @param {Number} from
 * @param {Number} to
 * @param {Object} rangeAttrs
 * @param {prosemirror-transaction} tr
 * @returns {prosemirror-transaction}
 *
 */
const replaceRange = (state, from, to, rangeAttrs, tr = false) => {
  /**
   * If a transaction is passed, we will use the state that will result from the transaction to check if any range splits
   * occur. If no transaction is passed, we will just use the current state.
   */
  const mappedState = tr?.doc || state.doc

  if (splitsExistingRange(from, to, mappedState)) {
    throw new Error('Ranges can not be split in two parts.')
  }

  return replaceMarkInRange(state, from, to, 'segmentsMark', rangeAttrs, tr)
}

/**
 * Used to remove a range mark within the specified positions.
 *
 * @param {prosemirror-state} state
 * @param {Number} from
 * @param {Number} to
 * @param {prosemirror-transaction} tr
 * @returns {prosemirror-transaction|Boolean}
 *
 */
const removeRange = (state, from, to, tr = false) => {
  const rangeMarkType = state.config.schema.marks.segmentsMark
  let transaction = tr || state.tr

  transaction = transaction.removeMark(from, to, rangeMarkType)

  return transaction
}

/**
 * Used to remove all marks of the specified type.
 *
 * @param {prosemirror-state} state
 * @param {String} markName
 * @param {String} markAttr
 * @param {prosemirror-transaction} tr
 * @returns {prosemirror-transaction}
 *
 */
const removeMarkByName = (state, markName, markAttr, tr = false) => {
  const nodes = flattenNode(state.doc)
  const marks = getMarks(nodes, markName, markAttr)
  const transaction = tr || state.tr
  const markType = state.config.schema.marks[markName]

  Object.values(marks).forEach(mark => {
    const { from, to } = mark
    transaction.removeMark(from, to, markType)
  })

  return transaction
}

/**
 *
 * Used to set the editing state of a range by id.
 */
const setRangeEditingState = (view, rangeTrackerKey, editingDecorationsKey) => (id, editingState) => {
  const { dispatch, state } = view
  const range = rangeTrackerKey.getState(state)[id]
  const { from, to, isConfirmed, rangeId } = range

  if (!range) {
    throw new Error('Range not found')
  }

  let tr = replaceRange(state, from, to, { rangeId, isConfirmed, isActive: editingState })
  tr = tr.setMeta(editingDecorationsKey, { editing: editingState, from, to, id })

  dispatch(tr)
}

/**
 * This is used to replace a mark in a specific range with a new mark.
 *
 * @param {prosemirror-state} state
 * @param {Number} from
 * @param {Number} to
 * @param {String} markKey
 * @param {Object} markAttrs
 * @param {Boolean} tr
 * @returns {prosemirror-transaction}
 *
 */
const replaceMarkInRange = (state, from, to, markKey, markAttrs, tr = false) => {
  const pmId = uuidv4()
  const newAttrs = { ...markAttrs, pmId }
  // Console.log('markKey', markKey)
  const markType = state.config.schema.marks[markKey]
  let transaction = tr || state.tr

  /**
   * We get range sizes (from, to) from an external service.
   * The size does not necessarily correspond to our prosemirror document size.
   * If we try to remove or add marks that exceed the document size, prosemirror will throw an error from 'nodesBetween'.
   * See: https://github.com/ProseMirror/prosemirror-model/pull/33
   * Therefore, we compare the document size here and truncate the range if it exceeds it.
   * This is ok because a range larger than the document would not have any content either way.
   */
  if (from > transaction.doc.content.size) {
    from = transaction.doc.content.size
    console.warn(`Range ${JSON.stringify(newAttrs)} was truncated from the start because it exceeded the document size.`)
  }
  if (to > transaction.doc.content.size) {
    to = transaction.doc.content.size
    console.warn(`Range ${JSON.stringify(newAttrs)} was truncated at the end because it exceeded the document size.`)
  }

  transaction = transaction.removeMark(from, to, markType)

  /*
   *Console.log('replaceMarkInRange - state.config.schema.marks', state.config.schema.marks)
   *console.log('markKey: ', markKey)
   *console.log('markType: ', markType)
   */

  const newMark = markType.create(newAttrs)
  transaction = transaction.addMark(from, to, newMark)
  const markCollection = getMarks(flattenNode(transaction.doc), markKey, 'pmId')
  // Console.log('markCollection: ', markCollection)
  const currentMarkCollection = markCollection[pmId]

  currentMarkCollection.marks.forEach(m => {
    transaction = transaction.removeMark(m.from, m.to, markType)
    const uniqueMark = markType.create({ ...newAttrs, pmId: uuidv4() })
    transaction = transaction.addMark(m.from, m.to, uniqueMark)
  })

  return transaction
}

/**
 * This command sets a range. You can use it as a curried function either passing in the view first and calling it later with
 * from, to and id. Or you can call it like this: setRange(view)(1, 5, 'an id').
 *
 * @param {prosemirror-view} view
 * @returns {undefined}
 *
 */
const setRange = (view) => (from, to, rangeAttrs) => {
  const { state, dispatch } = view
  const tr = replaceRange(state, from, to, rangeAttrs)

  dispatch(tr)
}

/**
 * This is a utility function generating decoration elements which indicate start and end positions of a range currently being edited.
 *
 * @param {String} id
 * @param {Boolean} isActive
 * @param {Number} pos
 * @returns {Node}
 *
 */
const makeDecoration = (id, pos, isActive = false) => {
  const el = document.createElement('span')
  el.setAttribute('data-range-widget', id)
  el.setAttribute('data-range-widget-pos', pos)

  el.innerHTML = `<span class="range-handle" data-range-widget-pos="${pos}" data-range-widget="${id}"><div class="range-handle__inner ${isActive ? 'is-active' : ''}" data-range-widget-pos="${pos}" data-range-widget="${id}"></div></span>`

  return el
}

/**
 * This command is used to generate a set of decorations which signal start and end point for a specific range.
 *
 * @param {prosemirror-state} state
 * @param {Number} from
 * @param {Number} to
 * @param {String} id
 * @param {Number|null} activePosition
 * @returns {prosemirror-decoration-set}
 *
 */
const genEditingDecorations = (state, from, to, id, activePosition = null) => {
  console.log('from to', from, to)
  const start = Decoration.widget(from, makeDecoration(id, from, activePosition === from), { id })
  const end = Decoration.widget(to, makeDecoration(id, to, activePosition === to), { id })

  return DecorationSet.create(state.doc, [
    start,
    end
  ])
}

/**
 * This activates the range editing mode so that handles can be moved.
 *
 * @param {prosemirror-view} view
 * @param {prosemirror-pluginkey} rangeTrackerKey
 * @param {prosemirror-pluginkey} editStateTrackerKey
 * @param {String} rangeId
 * @param {Number} activationPosition
 *
 */
const activateRangeEdit = (view, rangeTrackerKey, editStateTrackerKey, rangeId, positions = { active: null, fixed: null }) => {
  const { state, dispatch } = view
  let tr = state.tr

  /**
   * This block sets the cursor near the activated range handle. It then toggles the range editing mode by notifying
   * the editStateTracker-plugin via a meta message.
   */
  tr = tr.setSelection(TextSelection.near(state.doc.resolve(positions.active)))
  const range = rangeTrackerKey.getState(state)[rangeId]
  console.log('activateRangeEdit - range: ', range)
  tr = tr.setMeta(editStateTrackerKey, { id: rangeId, pos: positions.active, moving: true, positions: { active: range.to, fixed: range.from } })

  //  Tr = tr.setMeta(editStateTrackerKey, { id: rangeId, pos: positions.active, moving: true, positions })
  dispatch(tr)

  /**
   * Set range attributes to active and moving so that optional styling can be applied via CSS.
   */
  setRange(view)(range.from, range.to, { rangeId, isActive: true, isMoving: true, isConfirmed: range.isConfirmed })

  /**
   * The view needs to receive focus after activating range edit.
   */
  view.focus()
}

/**
 * This disables the range editing mode so that handles can't be moved anymore.
 *
 * @param {prosemirror-view} view
 * @param {prosemirror-pluginkey} editStateTrackerKey
 * @param {null|prosemirror-transaction} tr
 *
 */
const disableRangeEdit = (view, editStateTrackerKey, tr = null) => {
  const { state } = view
  let transaction = tr || state.tr
  transaction = transaction.setMeta(editStateTrackerKey, 'stop-editing')

  return transaction
}

/**
 *
 * @param {prosemirror-view} view
 * @param {prosemirror-pluginkey} rangeTrackerKey
 * @param {prosemirror-pluginkey} editStateTrackerKey
 * @param target
 */
const toggleRangeEdit = (view, rangeTrackerKey, editStateTrackerKey, decorationTrackerKey, target) => {
  const rangeId = target.getAttribute('data-range-widget')

  if (rangeId) {
    const { state } = view
    const pos = parseInt(target.getAttribute('data-range-widget-pos'))
    const currentlyActivePosition = decorationTrackerKey.getState(state).activeDecorationPosition

    /**
     * If the handle position equals the position of the currently active handle, the user clicked on the same handle
     * again and we do not need to perform any actions.
     */
    if (pos === currentlyActivePosition) {
      return true
    }

    /**
     * This code block is called whenever the user clicks a handle that is not already active. It moves the activation
     * state from the handle that is currently active to the handle which was just clicked.
     */
    const nodes = flattenNode(state.doc)
    const marks = getMarks(nodes, 'rangeselection', 'rangeType')
    const { from, to } = marks.selection
    const activationPosition = currentlyActivePosition === from ? to : from
    const fixedPosition = currentlyActivePosition === from ? from : to
    activateRangeEdit(view, rangeTrackerKey, editStateTrackerKey, rangeId, { active: activationPosition, fixed: fixedPosition })
  }
}

export {
  activateRangeEdit,
  applySelectionChange,
  disableRangeEdit,
  genEditingDecorations,
  setRange,
  setRangeEditingState,
  removeRange,
  replaceMarkInRange,
  removeMarkByName,
  replaceRange,
  toggleRangeEdit
}
