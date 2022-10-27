/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getScrollTop } from 'demosplan-utils'
import { prefixClass } from 'demosplan-ui/lib'

function getAllInputsArray (form) {
  return Array.from(form.querySelectorAll('[pattern], [required], input[type="email"], .tiptap__input--hidden.is-required, .multiselect.is-required, fieldset.is-required, [minlength], [maxlength], [data-dp-validate-maxlength], [data-dp-validate-should-equal]'))
}

function shouldValidate (input) {
  const isRequired = input.hasAttribute('required') || input.classList.contains('is-required')
  if (input.hasAttribute('data-dp-validate-if') && validateConditionsFulfilled(input) === false) {
    toggleErrorClass(input, false)
    return false
  }

  return !!(
    isRequired ||
    input.hasAttribute('pattern') ||
    input.getAttribute('type') === 'email' ||
    input.hasAttribute('minlength') ||
    input.hasAttribute('maxlength') ||
    input.hasAttribute('data-dp-validate-maxlength') ||
    input.hasAttribute('data-dp-validate-should-equal')
  )
}

function validateConditionsFulfilled (input) {
  const allConditions = input.getAttribute('data-dp-validate-if').split(',')

  return allConditions.some(condition => {
    const comparisonType = condition.indexOf('!==') > -1 ? 'isNotEqual' : 'isEqual'
    const matchers = condition.split(comparisonType === 'isNotEqual' ? '!==' : '===')
    const form = input.closest('[data-dp-validate]')

    try {
      const inputToCheck = form.querySelector(matchers[0]) // It has to be a valid querySelector, which means that numerical ids will throw an error
      if (inputToCheck.type === 'checkbox' || inputToCheck.type === 'radio') {
        return comparisonType === 'isNotEqual' ? !inputToCheck.checked : inputToCheck.checked
      } else {
        if (!inputToCheck.value) {
          return false
        }
        return comparisonType === 'isNotEqual' ? inputToCheck.value !== matchers[1] : inputToCheck.value === matchers[1]
      }
    } catch (e) {
      return false
    }
  })
}

function getInputType (input) {
  let type = 'input'
  if (input.classList.contains('multiselect')) {
    type = 'multiselect'
  } else if (input.classList.contains('tiptap__input--hidden')) {
    type = 'tiptap'
  } else if (input.tagName === 'FIELDSET') {
    type = 'fieldset'
  } else if (input.classList.contains('a1-input')) {
    type = 'datepicker'
  }
  return type
}

const errorClass = prefixClass('is-invalid')

function toggleErrorClass (input, shouldAddClass) {
  if (getInputType(input) === 'tiptap') {
    input = input.parentNode.querySelector('div[contenteditable="true"]')
  }

  // Uppy fileuploader DpUploadedFiles
  if (input.type === 'hidden' && input.name.startsWith('uploadedFiles')) {
    input = input.parentNode.querySelector('.uppy-Root')
  }
  if (shouldAddClass) {
    input.classList.add(errorClass)
  } else if (input.classList.contains(errorClass)) {
    input.classList.remove(errorClass)
  }
}

function awaitElement (selector, context) {
  return new Promise(resolve => {
    const element = context.querySelector(selector)
    if (element) {
      resolve(element)
    }
    new MutationObserver((mutationRecords, observer) => {
      // Query for elements matching the specified selector
      Array.from(context.querySelectorAll(selector)).forEach(element => {
        resolve(element)
        // Once we have resolved we don't need the observer anymore.
        observer.disconnect()
      })
    })
      .observe(document, {
        childList: true,
        subtree: true
      })
  })
}

function isElementInViewport (el) {
  const rect = el.getBoundingClientRect()
  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
  )
}

/**
 * Scrolls a node into view, if currently not in viewport.
 * If not visible in the dom, the visible previousSibling is scrolled to.
 * @param element
 */
function scrollToVisibleElement (element) {
  const visibleElement = getVisibleElement(element)
  if (isElementInViewport(visibleElement) === false) {
    const elementOffset = visibleElement.getBoundingClientRect().top + getScrollTop()
    window.scrollTo({
      behavior: 'smooth',
      top: elementOffset - 50
    })
  }
}

/**
 * Returns element if visible in the dom.
 * If not, search recursively for visible previousSibling.
 * @param element
 * @return {*|{offsetParent}}
 */
function getVisibleElement (element) {
  return element.offsetParent == null ? getVisibleElement(element.parentElement) : element
}

export { getAllInputsArray, shouldValidate, getInputType, awaitElement, errorClass, toggleErrorClass, scrollToVisibleElement }
