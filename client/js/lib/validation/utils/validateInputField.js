/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { shouldValidate, toggleErrorClass } from './helpers'
import { regexp } from './validateEmail'

export default function validateInput (input) {
  if (shouldValidate(input) === false) {
    return true
  }

  const errorLabel = input.getAttribute('data-dp-validate-error') || false

  // Initially set allValid to true
  let isValid = true

  // ##### SET CORRECT VALIDATION RULES (REGEXP) #####
  let inputPattern = input.getAttribute('pattern')
  let re = new RegExp(inputPattern)
  if (input.getAttribute('type') === 'email') {
    inputPattern = 'email'
    re = regexp
  }

  // ##### CHECK VALIDITY OF INPUT #####

  // step 1: check min and maxlength if defined - this is mainly to test textareas, which do not support pattern attribute
  if (input.getAttribute('minlength') && isValid) {
    inputPattern = null
    isValid = input.value.length >= parseInt(input.getAttribute('minlength'))
  }
  if (input.getAttribute('maxlength') && isValid) {
    inputPattern = null
    isValid = input.value.length <= parseInt(input.getAttribute('maxlength'))
  }

  /*
   * Step 2: check against pattern
   * if input is not required too, empty string is valid
   */
  if (inputPattern && isValid) {
    isValid = (input.hasAttribute('required') === false && input.value === '') ? true : re.test(input.value)
  }

  // Step 3. check if field has to match with the value of another field (e.g. confirm E-Mail)
  if (input.getAttribute('data-dp-validate-should-equal') && isValid) {
    isValid = input.value === document.querySelector(input.getAttribute('data-dp-validate-should-equal')).value
  }

  // Step 4: check if required inputs are not empty/checked
  if (input.hasAttribute('required') && isValid) {
    if (input.type === 'checkbox') {
      isValid = input.checked === true
    } else {
      isValid = input.value !== ''
    }
  }

  // ##### IF INPUT IS VALID #####
  if (isValid === true) {
    // Try to set customValidity (it will only work if input is visible, that is why try/catch is used)
    try {
      input.setCustomValidity('')
    } catch (err) {
      // Fail silently if setCustomValidity threw an error
    }
    // Remove aria-invalid
    if (input.hasAttribute('aria-invalid')) {
      input.removeAttribute('aria-invalid')
    }
    // Remove error border
    toggleErrorClass(input, false)
    return true
  } else {
    // ##### IF INPUT IS INVALID #####
    input.setAttribute('aria-invalid', true)
    toggleErrorClass(input, true)

    // Handle error messages - try to set customValidity (it will only work if input is visible, that is why try/catch is used)
    try {
      if (errorLabel) {
        // Custom error label from data-dp-validate-error
        input.setCustomValidity(Translator.trans(errorLabel))
      } else if (inputPattern) {
        // Error label based on pattern
        switch (inputPattern) {
          case '^[0-9]{5}$':
            input.setCustomValidity(Translator.trans('validation.error.zipcode'))
            break
          case '[A-Za-zÄäÜüÖöß ]+':
            input.setCustomValidity(Translator.trans('validation.error.city'))
            break
          case 'email':
            input.setCustomValidity(Translator.trans('validation.error.email'))
            break
          default:
            input.setCustomValidity(Translator.trans('validation.error.default'))
        }
      } else {
        input.setCustomValidity(Translator.trans('validation.error.default')) // Theoretically this must not happen hence its not that helpful
      }
    } catch (err) {
      // Fail silently if setCustomValidity threw an error
    }

    return false
  }
}
