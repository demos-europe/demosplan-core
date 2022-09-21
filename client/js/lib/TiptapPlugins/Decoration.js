/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Decoration, DecorationSet } from 'prosemirror-view'
import { Plugin, TextSelection } from 'prosemirror-state'

const addHandle = (tr, set, pos, id) => {
  const widget = document.createElement('span')
  widget.classList.add('handle')
  widget.setAttribute('data-position', pos)
  widget.innerHTML = `<span id="container" data-position="${pos}"><div id="bubble" data-position="${pos}"></div>|</span>`
  const deco = Decoration.widget(pos, widget, { id: id })
  set = set.add(tr.doc, [deco])
  return set
}

const removeHandle = (set, id) => {
  set = set.remove(set.find(null, null,
    spec => spec.id === id))
  return set
}

const placeholderPlugin = new Plugin({
  state: {
    init () { return DecorationSet.empty },
    apply (tr, set) {
      // Adjust decoration positions to changes made by the transaction
      set = set.map(tr.mapping, tr.doc)
      // See if the transaction adds or removes any placeholders
      const action = tr.getMeta(this)
      if (action && action.add) {
        set = addHandle(tr, set, action.add.pos, action.add.id)
      } else if (action && action.remove) {
        set = removeHandle(set, action.remove.id)
      } else if (action && action.move) {
        set = removeHandle(set, action.move.id)
        set = addHandle(tr, set, action.move.pos, action.move.id)
      }
      return set
    }
  },
  props: {
    decorations (state) { return this.getState(state) }
  },
  filterTransaction (tr, state) {
    const action = tr.getMeta(this)
    /**
     * The behaviour of text selections in prosemirror differs in Chrome and Firefox.
     * In our case, Chrome treats the current caret position as selection range while Firefox applies the correct selection
     * from initial caret position to current caret position.
     * To fix this behaviour we modify the transaction and set the correct selection in this method.
     */
    if (action && action.move) {
      tr.setSelection(new TextSelection(state.doc.resolve(action.move.initialPos), state.doc.resolve(action.move.pos)))
    }
    return true
  }
})

export { placeholderPlugin }
