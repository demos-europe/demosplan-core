/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import tippy, { sticky } from 'tippy.js'
import { Mention } from 'tiptap-extensions'

/**
 * A utility function to attach event listeners to the suggestion popup.
 *
 * @param args {Object} One or more objects containing eventType and callback { eventType: 'click', handler: () => ({}) }
 */
function attachEventListeners (...args) {
  const listWrapper = document.querySelector('[data-suggestion-id="suggestion-popup"]')
  args.forEach(({ eventType, handler }) => attachSingleEventListener(listWrapper, eventType, handler))
}

/**
 * A utility function to attach event listeners to an element.
 *
 * @param el {Element} A DOM Element that the event listener should be attached to.
 * @param eventType {String} An event type that should be listened to (e.g. 'click')
 * @param handler {Function} A callback that should be triggered when the event occurs.
 */
function attachSingleEventListener (el, eventType, handler) {
  if (el) {
    el.removeEventListener(eventType, handler)
    el.addEventListener(eventType, handler)
  }
}

/**
 * Used to highlight the currently selected suggestion item in the suggestion popup.
 *
 * @param listIndex {Number} The index of the suggestion item that should be highlighted.
 */
function highlightActiveElement (listIndex) {
  const wrapper = document.querySelector('[data-suggestion-id="suggestion-popup"]')
  if (wrapper) {
    const nodeList = wrapper.childNodes
    nodeList.forEach((node, idx) => idx === listIndex
      ? node.classList.add('suggestion__list-item--is-active')
      : node.classList.remove('suggestion__list-item--is-active'))
  }
}

/**
 * Used to generate content for the suggestion popup.
 *
 * @param items {Array} The suggestions that will be rendered insided the suggestion popup ([{ id: <id>, name: <name> }]).
 *
 * @return {HTMLUListElement}
 */
function createFragment (items) {
  const fragment = document.createElement('ul')
  fragment.setAttribute('data-suggestion-id', 'suggestion-popup')
  fragment.setAttribute('class', 'o-list suggestion__popup')

  for (let i = 0; i < items.length; i++) {
    const li = document.createElement('li')
    li.setAttribute('class', 'o-list__item suggestion__list-item u-ph-0_5 u-pv-0_25')
    li.setAttribute('data-suggestion-item', `${i}`)

    const button = document.createElement('button')
    button.setAttribute('class', 'btn--blank')
    button.appendChild(document.createTextNode(items[i].name))

    li.appendChild(button)
    fragment.appendChild(li)
  }
  if (items.length === 0) {
    const li = document.createElement('li')
    li.appendChild(document.createTextNode('Keine passenden Platzhalter gefunden'))
    fragment.appendChild(li)
  }

  return fragment
}

/**
 * Used to create a suggestion plugin which triggers on certain characters (e.g. @) and inserts a Mention node into tiptap
 * when a suggestion was selected.
 *
 * @param matcher {Object} The char that should trigger a suggestion and some config to determine possible suggestion trigger positions ({ char: <char>, allowSpaces: <boolean>, startOfLine: <boolean> }).
 * @param suggestions {Array} All available suggestions ([{ id: <id>, name: <name> }]).
 * @param vueEditorInstance {VueInstance} Used to focus the tiptap editor on insertion of a suggestion.
 *
 * @return {Mention} A mention extension that can be used by tiptap.
 */
function createSuggestion ({ matcher, suggestions }, vueEditorInstance) {
  /*
   *Adding the whitespace to the name is a hack to make the suggestions work
   *@see https://github.com/ueberdosis/tiptap/issues/932
   *after the upgrade to tiptap 2 this workaround should be checked again
   *and may hopefully be removed again
   */
  const fixedSuggestions = suggestions.map(el => ({ id: el.id, name: el.name + ' ' }))
  let popup = null
  let selectedElement = 0
  let filteredSuggestions = fixedSuggestions
  let insertSuggestion = (...args) => ({ args })
  let suggestionRange = null

  /**
   * Triggers insertion of a suggestion.
   */
  function clickHandler (e) {
    let el = e.target
    if (el.tagName === 'BUTTON') {
      el = el.parentElement
    }
    const itemIdx = parseInt(el.getAttribute('data-suggestion-item'))
    selectSuggestion(fixedSuggestions[itemIdx])
    vueEditorInstance.editor.focus()
  }

  /**
   * Removes the suggestion popup from the DOM.
   */
  function destroyPopup () {
    if (popup) {
      popup[0].destroy()
      popup = null
    }
  }

  /**
   * Increments the currently selected suggestion.
   */
  function downHandler () {
    if (selectedElement < fixedSuggestions.length - 1) {
      selectedElement += 1
      highlightActiveElement(selectedElement)
    }
  }

  /**
   * Triggers insertion of a suggestion.
   */
  function enterHandler () {
    selectSuggestion(fixedSuggestions[selectedElement])
  }

  /**
   * Used to render a popup containing applicable suggestions.
   *
   * @param items {Array} The suggestions that will be available to the suggestion popup ([{ id: <id>, name: <name> }]).
   * @param listIndex {Number} The index of the currently selected suggestion.
   * @param node {TipTapVirtualNode} A virtual node used for placement of the suggestion popup.
   */
  function renderPopup (items, listIndex, node) {
    const boundingClientRect = node.getBoundingClientRect()
    const { x, y } = boundingClientRect
    if (x === 0 && y === 0) {
      return
    }
    if (popup) {
      popup[0].setContent(createFragment(items))
    } else {
      popup = tippy('#mainContent', {
        getReferenceClientRect: () => boundingClientRect,
        appendTo: () => document.body,
        interactive: true,
        sticky: true, // Make sure position of tippy is updated when content changes
        plugins: [sticky],
        content: createFragment(items),
        trigger: 'manual', // Manual
        showOnCreate: true,
        theme: 'dark',
        placement: 'top-start',
        inertia: true,
        duration: [400, 200]
      })
    }
    highlightActiveElement(listIndex)
    attachEventListeners({ eventType: 'click', handler: clickHandler })
  }

  /**
   * Used to insert a suggestion into tiptap.
   *
   * @param suggestion {Object} The suggestion that should be inserted ({ id: <id>, name: <name> })
   */
  function selectSuggestion (suggestion) {
    insertSuggestion({
      range: suggestionRange,
      attrs: {
        id: suggestion.id,
        label: suggestion.name
      }
    })
  }

  /**
   * Triggers insertion of a suggestion.
   */
  function spaceHandler () {
    if (fixedSuggestions.length === 1) {
      selectSuggestion(fixedSuggestions[0])
    }
  }

  /**
   * Decrements the currently selected suggestion.
   */
  function upHandler () {
    if (selectedElement > 0) {
      selectedElement -= 1
      highlightActiveElement(selectedElement)
    }
  }

  return new Mention({
    items: fixedSuggestions,
    matcher: matcher,
    mentionClass: 'suggestion__node',
    // Is called when a suggestion starts
    onEnter: ({ items, range, command, virtualNode }) => {
      filteredSuggestions = items
      suggestionRange = range
      selectedElement = 0
      renderPopup(filteredSuggestions, selectedElement, virtualNode)
      insertSuggestion = command
    },
    // Is called when a suggestion has changed
    onChange: ({ items, range, virtualNode }) => {
      filteredSuggestions = items
      suggestionRange = range
      selectedElement = 0
      renderPopup(filteredSuggestions, selectedElement, virtualNode)
    },
    // Is called when a suggestion is cancelled
    onExit: () => {
      // Reset all saved values
      filteredSuggestions = []
      suggestionRange = null
      selectedElement = 0
      destroyPopup()
    },
    // Is called on every keyDown event while a suggestion is active
    onKeyDown: ({ event }) => {
      if (event.key === 'ArrowUp') {
        upHandler()
        return true
      }
      if (event.key === 'ArrowDown') {
        downHandler()
        return true
      }
      if (event.key === 'Enter') {
        enterHandler()
        return true
      }

      if (event.key === ' ') {
        spaceHandler()
        return true
      }
      return false
    }
  })
}

export { createSuggestion }
