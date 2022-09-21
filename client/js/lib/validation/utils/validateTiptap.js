/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { errorClass, shouldValidate, toggleErrorClass } from './helpers'

export default function validateTiptap (input) {
  if (shouldValidate(input) === false) {
    return true
  }
  let isValid = true
  if (
    (input.classList.contains('is-required') && input.value === '') ||
    (input.hasAttribute('data-dp-validate-maxlength') && input.getAttribute('data-dp-validate-maxlength') !== 0 && input.value.length > input.getAttribute('data-dp-validate-maxlength'))
  ) {
    isValid = false
    // Add error border to tiptap field and remove it on next focus
    const tiptapField = input.parentNode.querySelector('div[contenteditable="true"]')
    if (tiptapField.classList.contains(errorClass) === false) {
      toggleErrorClass(tiptapField, true)
      const removeBorder = () => {
        toggleErrorClass(tiptapField, false)
        tiptapField.removeEventListener('focus', removeBorder)
      }
      tiptapField.addEventListener('focus', removeBorder)
    }
  }
  return isValid
}
