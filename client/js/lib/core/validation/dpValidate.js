/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * This is a dpValidate plugin for form validation in vanilla JS.
 * Up to date documentation and usage examples can be found here:
 * https://yaits.demos-deutschland.de/w/demosplan/frontend-documentation/frontend-validierung/
 *
 * There is also a vue-mixin which adds dpValidate properties to the component.
 * See: @DemosPlanCoreBundle/lib/validation/dpValidateMixin.js
 */

import assignHandlerForTrigger from './utils/assignHandlerForTrigger'
import { assignHandlersForInputs } from './utils/assignHandlersForInputs'
import assignObserver from './utils/assignObserver'

export default function dpValidate (formId) {
  // If form element is passed as param take it, if not, look for all data-dp-validate elements
  let forms = []
  if (formId) {
    forms.push(document.querySelector(`[data-dp-validate=${formId}]`))
  } else {
    forms = Array.from(document.querySelectorAll('[data-dp-validate]'))
  }

  if (forms.length > 0) {
    forms.forEach(form => {
      assignHandlersForInputs(form) // Add event listeners for form inputs
      assignObserver(form) // Listen for new input fields in form, that also need validation

      const validationTrigger = form.querySelector('[type="submit"]:not([data-skip-validation])')
        ? Array.from(form.querySelectorAll('[type="submit"]:not([data-skip-validation])'))
        : Array.from(form.querySelectorAll('.submit:not([data-skip-validation])'))
      if (validationTrigger.length > 0) { // Add event listener to submit button/validation trigger
        validationTrigger.forEach(button => assignHandlerForTrigger(button, form))
      }
    })
  }
}
