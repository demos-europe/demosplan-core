/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getAllInputsArray, getInputType } from './helpers'
import validateDatepicker from './validateDatepicker'
import validateFieldset from './validateFieldset'
import validateInput from './validateInputField'
import validateMultiselect from './validateMultiselect'
import validateTiptap from './validateTiptap'

export default function validateForm (form) {
  let allValid = true
  const allInputsArray = getAllInputsArray(form)
  const allErrors = []
  allInputsArray.forEach(input => {
    const type = getInputType(input)
    let isInputValid = true
    switch (type) {
      case 'multiselect':
        isInputValid = validateMultiselect(input)
        break
      case 'tiptap':
        isInputValid = validateTiptap(input)
        break
      case 'fieldset':
        isInputValid = validateFieldset(input)
        break
      case 'datepicker':
        isInputValid = validateDatepicker(input)
        break
      case 'input':
      default:
        isInputValid = validateInput(input)
    }

    if (isInputValid === false) {
      allErrors.push(input)
      allValid = false
    }
  })

  return { valid: allValid, invalidFields: allErrors }
}
