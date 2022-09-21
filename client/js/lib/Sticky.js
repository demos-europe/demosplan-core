/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import Stickier from '@DpJs/lib/Stickier'
/**
 * A Wrapper providing a simple Dom API for stickier.js that once used semantic-ui-sticky under the hood
 * but was refactored to get rid of the jQuery dependency.
 *
 * @deprecated use DpStickyElement.vue instead
 */
export default function Sticky () {
  const stickies = document.querySelectorAll('[data-sticky]')

  if (stickies.length > 0) {
    console.warn('Found usage of deprecated stickier implementation, use <DpStickyElement> instead')
  }

  const stickierElements = []

  Array.from(stickies).forEach(stickyElement => {
    const contextElement = getElementByDataAttrReference(stickyElement, 'context')
    const containerElement = getElementByDataAttrReference(stickyElement, 'container')
    const elementOffset = parseInt(stickyElement.getAttribute('data-sticky-offset') || 0)
    const stickToDirection = 'top'
    const stickFromBreakpoint = stickyElement.getAttribute('data-sticky') || null

    stickierElements.push(new Stickier(
      stickyElement,
      contextElement,
      elementOffset,
      stickToDirection,
      stickFromBreakpoint,
      containerElement
    ))
  })

  return stickierElements
}

/**
 * Returns element specified in `data-sticky-<key>` via its Id.
 * @param element
 * @param key
 * @return {HTMLElement}
 */
const getElementByDataAttrReference = (element, key) => {
  const id = element.getAttribute(`data-sticky-${key}`) || null
  return id ? document.getElementById(id) : null
}
