/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DOMPurify from 'dompurify'

/**
 * Checks the type of value and always returns an object to be used with setInnerHTML.
 * If a string is passed, its value is used as content.
 * @param value {Object<content, options>|String}
 * @return {{content}|*}
 */
const getOptions = (value) => {
  const type = typeof value
  if (type === 'string') {
    return {
      content: value
    }
  } else if (type === 'object') {
    return value
  }
}

/**
 * Apply a sanitized string to the innerHtml of element.
 * @param el
 * @param binding
 */
const setSanitizedInnerHTML = (el, binding) => {
  const { content, options = {} } = getOptions(binding.value)
  el.innerHTML = DOMPurify.sanitize(content, options)
}

const CleanHtml = {
  bind: function (el, binding) {
    setSanitizedInnerHTML(el, binding)
  },
  update: function (el, binding) {
    if (binding.value !== binding.oldValue) {
      setSanitizedInnerHTML(el, binding)
    }
  }
}

export { CleanHtml }
