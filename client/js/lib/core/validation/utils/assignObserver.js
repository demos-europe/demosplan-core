/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { errorClass, getAllInputsArray, shouldValidate } from './helpers'
import { assignHandlersForSingleInput } from './assignHandlersForInputs'

export default function assignObserver (form) {
  // Check if new inputs have been added to DOM and add event handlers to them too
  const target = form
  const subscriber = (mutations) => {
    mutations.forEach((mutation) => {
      // Handle mutations here
      switch (mutation.type) {
        case 'childList':
          if (mutation.addedNodes.length > 0) {
            const addedNode = Array.from(mutation.addedNodes).filter(node => node.nodeType === 1)[0]
            if (!addedNode) {
              return false
            }
            if (shouldValidate(addedNode)) {
              // If the node itself is an input add handlers directly to it
              assignHandlersForSingleInput(addedNode)
            } else if (addedNode.children.length > 0 && getAllInputsArray(addedNode).length > 0) {
              // If it's not an input then look through its children and find all inputs that we want to validate
              getAllInputsArray(addedNode).forEach(childNode => {
                assignHandlersForSingleInput(childNode)
              })
            }
          }
          break
        case 'attributes':
          if ((mutation.target.tagName === 'INPUT' || mutation.target.tagName === 'TEXTAREA') && (mutation.attributeName === 'required' || mutation.attributeName === 'pattern')) {
            if (mutation.oldValue === null) { // If required is now set, assign validation handlers
              assignHandlersForSingleInput(mutation.target)
            } else if (wasAttributeRemoved(mutation)) { // If required was removed, replace the node with it's clone to remove all event listeners
              const nodeClone = mutation.target.cloneNode()
              nodeClone.classList.remove(errorClass)
              nodeClone.setCustomValidity('')
              mutation.target.parentNode.replaceChild(nodeClone, mutation.target)
            }
          }
          break
        default:
          break
      }
    })
  }
  const config = {
    attributes: true,
    attributeOldValue: true,
    childList: true,
    subtree: true
  }
  const observer = new MutationObserver(subscriber)
  observer.observe(target, config)

  function wasAttributeRemoved (mutation) {
    let wasRemoved = false
    const node = mutation.target
    if (mutation.attributeName === 'required') {
      wasRemoved = mutation.oldValue === 'required' && node.hasAttribute('required') === false
    } else if (mutation.attributeName === 'pattern') {
      wasRemoved = mutation.oldValue && (node.hasAttribute('pattern') === false || node.getAttribute('pattern') === mutation.oldValue)
    }
    return wasRemoved
  }
}
