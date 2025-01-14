/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import {
  createCreatorMenu,
  flattenNode,
  generateRangeChangeMap,
  getMarks,
  getMinMax,
  isSuperset,
  range,
  rangesEqual,
  serializeRange
} from './utilities'
import { genEditingDecorations, removeMarkByName, replaceMarkInRange, toggleRangeEdit } from './commands'
import { Plugin, PluginKey } from 'prosemirror-state'
import { rangeMark, rangeSelectionMark } from './marks'
import { Schema } from 'prosemirror-model'

/**
 *
 * This plugin manages the insertion of decorations indicating start and end points for a specific range.
 * It is also responsible for moving decorations, whenever the user changes cursor position inside the prosemirror editor.
 *
 * How does it work?
 * The plugin generates 2 decorations (handles) at the start and end of a range. One of these handles is 'active', meaning
 * that it will be moved when the cursor position changes. The plugin handles a mousedown-event and changes the active
 * handle whenever the user clicks on the other handle.
 *
 * Furthermore, the plugin appends each transaction. Appending a transaction means that it pushes another transaction
 * that will be processed after the previous transaction was applied. In the appendTransaction-method this plugin reaches
 * into the editStateTracker-plugin. If there are active handles, the editStateTrackerPlugin will hold the
 * starting and end positions of the current selection. This plugin will then generate a new rangeselection mark,
 * which corresponds to the new positions. The rangeselection mark is used to show the current selection (i.e. the new
 * range extent) inside the editor. If the edit mode is disabled, the appendTransaction-method will remove all remaining
 * rangeselection marks.
 *
 * The re-positioning of the handles is done inside this plugins apply method. If the handles shall be moved, there is
 * a meta-property for this plugin which holds the new positions of the handles. The plugin then generates 2 new decorations
 * and returns them as its new pluginstate, which causes them to be displayed at their new positions.
 *
 * @param {prosemirror-pluginKey} pluginKey
 * @param {prosemirror-pluginKey} editingTrackerKey
 * @param {prosemirror-command} setDecorations
 * @param {prosemirror-pluginKey} rangeTrackerKey
 * @returns {prosemirror-plugin}
 *
 */
const editingDecorations = (pluginKey, editingTrackerKey, rangeTrackerKey, editToggleCallback) => {
  return new Plugin({
    callbackPayload: null,
    callbackRunning: false,
    key: pluginKey,
    state: {
      init () {
        return {
          isEditing: false,
          decorations: null,
          position: {},
          activeDecorationPosition: null
        }
      },
      apply (tr, pluginState, _, newState) {
        const meta = tr.getMeta(pluginKey)
        const move = editingTrackerKey.getState(newState)

        if (meta && meta.editing) {
          /**
           * In case prosemirror updates are triggered inside the callback we want to avoid triggering the callback again.
           * This does not allow the calling code to react to changes made by their own callback but it removes
           * the burden of circumventing endless loops from the caller.
           */
          if (!this.spec.callbackRunning) {
            this.spec.callbackPayload = [rangeTrackerKey.getState(newState)[meta.id], true]
          }

          const decos = genEditingDecorations(newState, meta.from, meta.to, meta.id)

          return {
            isEditing: true,
            decorations: decos,
            position: { from: meta.from, to: meta.to },
            activeDecorationPosition: null
          }
        } else if (meta && !meta.editing) {
          /**
           * In case prosemirror updates are triggered inside the callback we want to avoid triggering the callback again.
           * This does not allow the calling code to react to changes made by their own callback but it removes
           * the burden of circumventing endless loops from the caller.
           */
          if (!this.spec.callbackRunning) {
            this.spec.callbackPayload = [rangeTrackerKey.getState(newState)[meta.id], false]
          }

          return {
            isEditing: false,
            decorations: pluginState.decorations.remove(pluginState.decorations.find(0, newState.doc.nodeSize)),
            position: {},
            activeDecorationPosition: null
          }
        } else if (move.moving) {
          const { fixed } = move.positions
          const selection = tr.selection
          const newDecorationPosition = selection.$head.pos

          const { from, to } = getMinMax(fixed, newDecorationPosition)

          return {
            isEditing: true,
            decorations: genEditingDecorations(newState, from, to, move.id, newDecorationPosition),
            position: { from, to },
            activeDecorationPosition: newDecorationPosition
          }
        } else {
          return pluginState
        }
      }
    },
    props: {
      decorations (state) {
        return pluginKey.getState(state).decorations
      },
      handleDOMEvents: {
        mousedown (view, e) {
          const isHandle = e.target.getAttribute('data-range-widget') !== null
          /**
           * Here, we need to check if the user clicked on a handle. If a handle was clicked, we need to prevent the
           * default behaviour. If we would not prevent the default behaviour, a prosemirror selection would be set close
           * to the handle which would change the extent of the active range.
           */
          if (isHandle) {
            e.preventDefault()
          }
          toggleRangeEdit(view, rangeTrackerKey, editingTrackerKey, pluginKey, e.target)

          return true
        }
      }
    },
    appendTransaction (_, oldState, newState) {
      const position = pluginKey.getState(newState).position
      const move = editingTrackerKey.getState(newState)

      /**
       * If a rangeselection handle is clicked, the from and to positions are temporarily set to the same value.
       * We do not want to change the rangeselection in that case because this would lead to errors.
       */
      if (position.from && position.to && position.from === position.to) {
        return
      }

      /**
       * This adjusts the rangeselection mark to the new handle positions and removes the rangeselection if the edit
       * mode was deactivated.
       */
      if (Object.keys(position).length && move.moving) {
        let tr = removeMarkByName(newState, 'rangeselection', 'active')
        tr = replaceMarkInRange(newState, position.from, position.to, 'rangeselection', { active: true }, tr)
        return tr
      } else {
        const oldEditState = pluginKey.getState(oldState).isEditing
        const newEditState = pluginKey.getState(newState).isEditing
        if (oldEditState && !newEditState) {
          const tr = removeMarkByName(newState, 'rangeselection', 'active')
          return tr
        }
      }
    },
    view (view) {
      let updateFunc = (view, state) => {
        if (this.callbackPayload) {
          const payload = [...this.callbackPayload]
          this.callbackPayload = null
          this.callbackRunning = true
          editToggleCallback(...payload)
          this.callbackRunning = false
        }
      }
      // We need to bind the context of the view method to the updateFunc so that we can access the context when calling it.
      updateFunc = updateFunc.bind(this)

      return {
        update: updateFunc
      }
    }
  })
}

/**
 *
 * This plugin tracks which range is currently being edited.
 *
 * @param {prosemirror-pluginKey} trackerKey
 * @param {prosemirror-pluginkey} decoPluginKey
 * @returns {prosemirror-plugin}
 *
 */
const editStateTracker = (trackerKey, decoPluginKey) => {
  return new Plugin({
    key: trackerKey,
    state: {
      init () {
        return {
          id: null,
          moving: null,
          pos: null,
          positions: null
        }
      },
      apply (tr, pluginState) {
        const meta = tr.getMeta(trackerKey)
        const editMeta = tr.getMeta(decoPluginKey)

        const defaultReturnVal = {
          id: null,
          moving: null,
          pos: null,
          positions: null
        }
        let returnVal = pluginState
        if (meta) {
          returnVal = meta === 'stop-editing' ? defaultReturnVal : meta
        }
        if (editMeta?.editing === false) {
          returnVal = defaultReturnVal
        }

        return returnVal
      }
    }
  })
}

/**
 *
 * This plugin holds the state of all ranges currently used in a specific prosemirror instance.
 * A callback function can be passed as an argument which will be called whenever the state updates.
 * The callback receives the old range state and the updated range state as arguments.
 *
 * @param {prosemirror-pluginKey} rangeTrackerKey
 * @param {prosemirror-schema} schema
 * @param {Function} rangeChangeCallback
 * @returns {prosemirror-plugin}
 *
 */
const rangeTracker = (rangeTrackerKey, schema, rangeChangeCallback = () => {}) => {
  return new Plugin({
    callbackPayload: null,
    callbackRunning: false,
    key: rangeTrackerKey,
    state: {
      init (_, state) {
        const ranges = getMarks(flattenNode(state.doc), 'range', 'rangeId')

        return ranges
      },
      apply (_, pluginState, oldState, newState) {
        if (oldState.doc.eq(newState.doc)) {
          return pluginState
        }

        const ranges = getMarks(flattenNode(newState.doc), 'range', 'rangeId')
        const equal = ranges && pluginState && rangesEqual(pluginState, ranges)
        if (equal) {
          return pluginState
        }

        if (!equal) {
          /**
           * We extract the text from each range and add it as a property so that range text can be handled separately
           * from the whole document. For example, this allows us to create segments from ranges later on. Prosemirror
           * will handle correct serialization for us and it will make sure that we get valid HTML, even if a selected
           * range cuts through nodes.
           *
           * However, for the serialization part, we first remove our range and rangeselection marks from the schema
           * that we use for parsing because we don't want those to appear in the text that we extract.
           * Under schema.spec.marks we will find an OrderedMap of marks in the current schema (https://github.com/marijnh/orderedmap#readme),
           * the OrderedMap lets us remove entries by calling its subtract method with the keys that we'd like to remove.
           */
          const rangeMarksRemoved = schema.spec.marks.subtract({ rangeselection: null, range: null })
          const reducedSchema = new Schema({
            nodes: schema.spec.nodes,
            marks: rangeMarksRemoved
          })
          Object.entries(ranges).forEach(([id, range]) => {
            ranges[id].text = serializeRange(range, newState, reducedSchema)
          })

          /**
           * In case prosemirror updates are triggered inside the callback we want to avoid triggering the callback again.
           * This does not allow the calling code to react to changes made by their own callback but it removes
           * the burden of circumventing endless loops from the caller.
           */
          if (!this.spec.callbackRunning) {
            const rangeChangeMap = generateRangeChangeMap(pluginState, ranges)
            this.spec.callbackPayload = [pluginState, ranges, rangeChangeMap]
          }

          return ranges
        }
      }
    },
    view (view) {
      let updateFunc = (view, state) => {
        if (this.callbackPayload) {
          const payload = [...this.callbackPayload]
          this.callbackPayload = null
          this.callbackRunning = true
          rangeChangeCallback(...payload)
          this.callbackRunning = false
        }
      }
      // We need to bind the context of the view method to the updateFunc so that we can access the context when calling it.
      updateFunc = updateFunc.bind(this)

      return {
        update: updateFunc
      }
    }
  })
}

/**
 * This function initializes a plugin which allows a user to create new ranges. When selecting unmarked text, a bubble
 * menu will pop up and the user will be able to add a range to the current document.
 *
 * @param {prosemirror-pluginkey} pluginKey
 * @param {prosemirror-pluginkey} rangeEditingKey
 * @return {prosemirror-plugin}
 */
const rangeCreator = (pluginKey, rangeEditingKey) => {
  let tippy = null
  return new Plugin({
    key: pluginKey,
    props: {
      handleDOMEvents: {
        mouseup (view, e) {
          const { state } = view
          const { selection } = state
          const { empty, from, to, $anchor, $head } = selection

          if (empty) {
            tippy?.destroy()
            tippy = null
            return
          }

          const lastClick = view.lastClick || view.input.lastClick

          if (e.clientX !== lastClick.x || e.clientY !== lastClick.y) {
            const existingRanges = rangeEditingKey.getState(state)
            let positionsCovered = []
            Object.values(existingRanges).forEach(({ from, to }) => positionsCovered.push(...range(from, to)))
            const selectedPositions = new Set(range(from, to))
            positionsCovered = new Set(positionsCovered)
            const isFullyCovered = isSuperset(positionsCovered, selectedPositions)

            if (isFullyCovered) {
              tippy?.destroy()
              tippy = null
              return
            }
            tippy?.destroy()
            tippy = createCreatorMenu(view, $anchor.pos, $head.pos)
          }
        }
      }
    }
  })
}

/**
 * This function initializes all plugins needed for the segmentation use-case. It will prepare the prosemirror-schema
 * so that marks indicating a range or a range selection are supported. It will also return all initialized plugins
 * which need to be passed on when initializing the prosemirror instance. The keys attribute returns prosemirror
 * pluginkeys. These keys can be used to access the internal state of the plugin.
 *
 * @param {prosemirror-schema} schema
 * @param {function} rangeChangeCallback
 * @param {function} editToggleCallback
 * @return {{schema, plugins: (prosemirror-plugin|*)[], keys: {rangeTrackerKey, editingDecorationsKey, rangeCreatorKey, editStateTrackerKey}}}
 */
const initRangePlugin = (schema, rangeChangeCallback, editToggleCallback) => {
  let marks = schema.spec.marks.addToStart('range', rangeMark)
  marks = marks.addToStart('rangeselection', rangeSelectionMark)
  const currentSchema = new Schema({
    nodes: schema.spec.nodes,
    marks
  })

  const editingDecorationsKey = new PluginKey('editing-decorations')
  const rangeTrackerKey = new PluginKey('range-tracker')
  const editStateTrackerKey = new PluginKey('range-edit-state')
  const rangeCreatorKey = new PluginKey('range-creator')

  const decoPlugin = editingDecorations(editingDecorationsKey, editStateTrackerKey, rangeTrackerKey, editToggleCallback)
  const editStatePlugin = editStateTracker(editStateTrackerKey, editingDecorationsKey)
  const rangeTrackerPlugin = rangeTracker(rangeTrackerKey, currentSchema, rangeChangeCallback)
  const rangeCreatorPlugin = rangeCreator(rangeCreatorKey, rangeTrackerKey)

  return {
    plugins: [editStatePlugin, rangeTrackerPlugin, decoPlugin, rangeCreatorPlugin],
    schema: currentSchema,
    keys: {
      rangeTrackerKey,
      editStateTrackerKey,
      editingDecorationsKey,
      rangeCreatorKey
    }
  }
}

export { initRangePlugin }
