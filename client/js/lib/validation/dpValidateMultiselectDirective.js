/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import validateMultiselect from './utils/validateMultiselect'

const dpValidateMultiselectDirective = {
  inserted (el, binding, vnode) {
    // Set initially correct validity value (no dropdown options => isValid)
    const component = vnode.componentInstance
    const hasOptions = component.groupValues ? component.options.some(group => group[component.groupValues].length > 0) : component.options.length > 0
    el.setAttribute('data-dp-validate-is-valid', !hasOptions)
    const validateMultiselectField = (e) => {
      e.stopPropagation()
      const validate = (e) => {
        document.removeEventListener('mouseup', validate)
        validateMultiselect(el)
      }
      document.addEventListener('mouseup', validate)
    }
    el.addEventListener('mouseup', validateMultiselectField)
  },

  componentUpdated (el, binding, vnode) {
    const component = vnode.componentInstance
    const hasOptions = component.groupValues ? component.options.some(group => group[component.groupValues].length > 0) : component.options.length > 0
    let isValid = checkValue(component.value)
    if (hasOptions === false) {
      isValid = true
    }
    el.setAttribute('data-dp-validate-is-valid', isValid)
  }
}

function checkValue (val) {
  if (!val) {
    return false
  }
  let isValid
  if (Array.isArray(val)) { // If it is a multiple select, value is Array
    if (val.length === 0) {
      isValid = false
    } else if (val.length === 1) {
      isValid = typeof val[0] === 'object' ? val[0].id !== '' : val[0] !== '' // Each value in array can be either an object or a string
    } else {
      isValid = true
    }
  } else if (typeof val === 'object') { // In single selects value is object or string
    isValid = val === null ? false : (Object.keys(val).length > 0 && val.id !== '') // We have to check if id is not empty, because sometime we have an 'empty option' in dropdowns and if the input is required the empty option is not a valid input
  } else if (typeof val === 'string') { // In single selects value is object or string
    isValid = val !== ''
  }
  return isValid
}

export default dpValidateMultiselectDirective
