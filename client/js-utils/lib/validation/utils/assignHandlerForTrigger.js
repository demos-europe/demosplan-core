/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { addFormHiddenField } from 'demosplan-utils/lib/FormActions'
import { scrollToVisibleElement } from './helpers'
import validateForm from './validateForm'

const submitAction = (form, triggerName) => {
  document.dispatchEvent(new CustomEvent('customValidationPassed', { detail: { form: form } }))
  if (typeof form.submit === 'function') {
    if (triggerName) {
      addFormHiddenField(form, triggerName, triggerName)
    }

    form.submit()
  }
}

export default function assignHandlerForTrigger (triggerButton, form) {
  triggerButton.addEventListener('click', (e) => {
    const callback = triggerButton.dataset.dpValidateCallback ? triggerButton.dataset.dpValidateCallback : ''
    const transKey = triggerButton.dataset.dpValidateTranskey ? triggerButton.dataset.dpValidateTranskey : ''
    const triggerName = triggerButton.getAttribute('name')
    const captureClick = triggerButton.hasAttribute('data-dp-validate-capture-click')
    const validatedForm = validateForm(form)
    e.preventDefault()
    if (validatedForm.valid === false) {
      if (captureClick) {
        e.stopImmediatePropagation()
      }
      // Display custom error messages defined via `data-dp-validate-error` as notifications
      const invalidCustomErrorFields = validatedForm.invalidFields.filter(field => field.dataset.dpValidateError)
      invalidCustomErrorFields.forEach(field => {
        dplan.notify.notify('error', Translator.trans(field.validationMessage))
      })
      // Make sure the default notification is displayed as needed
      if (invalidCustomErrorFields.length === 0 || invalidCustomErrorFields.length < validatedForm.invalidFields) {
        dplan.notify.notify('error', Translator.trans('error.mandatoryfields.no_asterisk'))
      }
      scrollToVisibleElement(validatedForm.invalidFields[0])
      document.dispatchEvent(new CustomEvent('customValidationFailed', { detail: { form: form } }))
    } else {
      switch (callback) {
        case 'dpconfirm':
          if (window.dpconfirm(transKey, true) === false) {
            e.preventDefault()
          } else {
            submitAction(form, triggerName)
          }
          break
        default:
          submitAction(form, triggerName)
      }
    }
  })
}
