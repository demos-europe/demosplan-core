/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 *  TableWrapper
 *  Wraps tables with a div to make them scrollable on mobile.
 *  Must be done with js because the content is user generated.
 *  The value of the data attribute is applied as class to the wrapping div.
 *
 *  Markup Examples
 *  --------------------------------------------------------------------------------
 *
 *  <element data-table-wrapper="overflow-x-auto">
 *
 */
import { prefixClass } from 'demosplan-ui/lib'

const wrapElement = function (element, wrapper) {
  element.parentNode.insertBefore(wrapper, element)
  wrapper.appendChild(element)
}

const createWrapper = function (targetClass) {
  const wrapper = document.createElement('div')
  wrapper.classList.add(targetClass)
  return wrapper
}

const getElementsOfType = function (nodeList, tagName) {
  return Array.from(nodeList).filter(node => node.tagName === tagName.toUpperCase())
}

export default function () {
  const containerElements = document.querySelectorAll('[data-table-wrapper]')

  for (let i = 0; i < containerElements.length; i++) {
    const containerElement = containerElements[i]
    const targetClass = prefixClass(containerElement.dataset.tableWrapper)
    const elements = getElementsOfType(containerElement.children, 'table')
    elements.forEach((element) => {
      const wrapperElement = createWrapper(targetClass)
      wrapElement(element, wrapperElement)
    })
  }
}
