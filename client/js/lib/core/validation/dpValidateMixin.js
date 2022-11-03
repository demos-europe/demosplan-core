/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * This is the dpValidate vue mixin that allows us to validate inputs in vue components. It is to be used in vue components. To use it in vanilla JS-context see: @DemosPlanCoreBundle/lib/validation/dpValidate.js
 *
 * Up to date documentation and usage examples can be found here:
 * https://yaits.demos-deutschland.de/w/demosplan/frontend-documentation/frontend-validierung/
 */
import { errorClass, scrollToVisibleElement } from './utils/helpers'
import { assignHandlersForInputs } from './utils/assignHandlersForInputs'
import { hasOwnProp } from 'demosplan-utils'
import validateForm from './utils/validateForm'

export default {
  data () {
    return {
      dpValidate: {}
    }
  },

  methods: {
    /**
     *
     * @param formId
     * @param callback
     * @param forceCallback   if true, the callback function is called even if the form is invalid
     */
    dpValidateAction (formId, callback, forceCallback = true) {
      const isForm = this.$el.hasAttribute('data-dp-validate') && this.$el.getAttribute('data-dp-validate') === formId
      const form = isForm ? this.$el : this.$el.querySelector(`[data-dp-validate=${formId}]`)
      const formValidation = validateForm(form)
      const newValidateObject = { ...this.dpValidate, [formId]: formValidation.valid }
      this.dpValidate = newValidateObject

      if (hasOwnProp(this.dpValidate, 'invalidFields') === false) {
        this.dpValidate.invalidFields = {}
      }
      this.dpValidate.invalidFields[formId] = formValidation.invalidFields
      if (this.dpValidate[formId] === false) {
        const customErrors = this.dpValidate.invalidFields[formId]
          .filter(element => element.hasAttribute('data-dp-validate-error'))
          .map(element => element.dataset.dpValidateError)
        customErrors.forEach(error => dplan.notify.notify('error', Translator.trans(error)))
        if (customErrors.length === 0) {
          dplan.notify.notify('error', Translator.trans('error.mandatoryfields.no_asterisk'))
        }
        const firstErrorElement = form.querySelector('.' + errorClass)
        scrollToVisibleElement(firstErrorElement)
      }

      // In some cases, we want to call the callback even if the form is invalid
      if ((this.dpValidate[formId] === false && forceCallback) || this.dpValidate[formId]) {
        return callback()
      }
    }
  },

  mounted () {
    // Set the initial values for form validity to true and add event listener for input fields (focus and blur)
    let forms = []
    if (this.$el.hasAttribute('data-dp-validate')) {
      this.isComponentForm = true // It means a whole component is a form to be validated; it has no child-forms
      forms.push(this.$el)
    } else {
      this.isComponentForm = false // It means in this component there are several forms that need to be validated
      forms = Array.from(this.$el.querySelectorAll('[data-dp-validate]'))
    }

    this.dpValidateForms = forms

    this.dpValidateForms.forEach(form => {
      const formId = form.getAttribute('data-dp-validate')
      this.dpValidate[formId] = true
      assignHandlersForInputs(form)
    })
  },

  updated () {
    this.$nextTick(() => {
      // Check if new forms were added - only if component is not a form itself
      if (this.isComponentForm === false) {
        // Collect all forms, that are in DOM after the update
        const afterUpdateForms = Array.from(this.$el.querySelectorAll('[data-dp-validate]'))
        // Merge forms that are currently in DOM with the forms that were previously in DOM
        const allForms = afterUpdateForms.concat(this.dpValidateForms)
        // Loop over all forms
        allForms.forEach(form => {
          if (this.dpValidateForms.includes(form) && afterUpdateForms.includes(form) === false) {
            // Remove form from dpValidateForms if it is no longer in DOM
            const idx = this.dpValidateForms.findIndex(el => el === form)
            delete this.dpValidateForms[idx]
          } else if (this.dpValidateForms.includes(form) === false && afterUpdateForms.includes(form)) {
            // If it is a new form (was just added to DOM), add it to dpValidateForms and assign handlers to each field
            this.dpValidateForms.push(form)
            const formId = form.getAttribute('data-dp-validate')
            this.dpValidate[formId] = true
            assignHandlersForInputs(form)
          } else {
            // If the form was previously in DOM and was not removed, reassign handlers as the required/validateIf/pattern properties may have changed
            assignHandlersForInputs(form)
          }
        })
      } else {
        // If component is a form, only reassign handlers in existing forms
        this.dpValidateForms.forEach(form => {
          assignHandlersForInputs(form)
        })
      }
    })
  }
}
