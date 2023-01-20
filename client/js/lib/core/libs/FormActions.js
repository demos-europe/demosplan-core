/**
 *  FormActions
 *  Several Forms Helpers
 *
 *  --------------------------------------------------------------------------------
 *
 *  Markup Example [data-form-actions-confirm]:
 *
 *  <button :data-form-actions-confirm="Translator.trans('check.statement.marked.submit')">
 *      {{ Translator.trans('statements.marked.submit') }}
 *  </button>
 *
 *  -------------------------------------------------------------------------------
 *
 *  Markup Example [data-form-actions-<manualsort|print|pdf-export>]:
 *
 *  <input type="submit" data-form-actions-<manualsort|print|pdf-export>="item_check[]">
 *
 *  --------------------------------------------------------------------------------
 *
 *  Markup Example [data-form-actions-submit-target]:
 *
 *  <input data-form-actions-submit-target="#buttonName" type="text" name="inputName">
 *
 *  <button class="btn" name="buttonName" value="{{ tag.id }}" id="buttonName">
 *      {{ Translator.trans('key') }}
 *  </button>
 *
 *
 */

/**
 *  Append hidden form field to a form
 *  @param form DOM element
 *  @param fieldname string
 *  @param value string
 */
function addFormHiddenField (form, fieldname, value) {
  //  Append new hidden field reflecting current form action
  const input = document.createElement('input')
  setAttributes(input, { type: 'hidden', name: fieldname, value: value, 'data-form-actions-added': '1' })

  form.appendChild(input)
}

/**
 *  Remove hidden form field from DOM
 *  @param form DOM element
 */
function removeFormHiddenField (form) {
  const formSelector = form.name !== '' ? '[name=' + form.name + ']' : '[id=' + form.id + ']'
  const target = Array.from(document.querySelectorAll(`${formSelector} [data-form-actions-added]`))
  target.forEach(el => {
    el.parentNode.removeChild(el)
  })
}

/**
 * Toggle all checkboxes to print only filtered/selected items inside a form
 * @param form
 * @param checkboxSelector
 */
function toggleToPrint (form, checkboxSelector) {
  checkboxSelector = checkboxSelector || ''
  const checkboxes = Array.from(document.querySelectorAll('checkbox' + checkboxSelector))
  const selectedCheckboxes = Array.from(document.querySelectorAll('checkbox'))

  /*
   * If no checkboxes have been checked by user,
   * check all
   */
  if (selectedCheckboxes.length === 0) {
    checkboxes.forEach(el => {
      el.setAttribute('checked', true)
    })
  }
}

function FormActions () {
  (function () {
    /*
     *  Listen to error event from dpValidate to remove hidden fields previously added by
     *  FormActionsConfirm, formActionsManualSort, formActionsPrint on customValidationFailed
     */
    document.addEventListener('customValidationFailed', (e) => {
      const form = e.detail.form
      try {
        removeFormHiddenField(form)
      } catch (err) {
        // Sometimes it will fail and it is ok - for example when form is a step in settings wizard, because then the form has no name or id
        console.warn('customValidationFailed event listener in FormActions.js', err)
      }
    })

    /**
     * Trigger a window.confirm() before form submission, add hidden field
     * With same name attr as self and a value of 1 to specify form action in controller
     *
     * @TODO Refactor form actions to make it unnecessary to add a hidden field
     */
    const formActionsConfirm = Array.from(document.querySelectorAll('[data-form-actions-confirm]'))
    formActionsConfirm.forEach((element) => {
      element.addEventListener('click', ev => {
        //  Get confirm message from data attr
        const msg = element.getAttribute('data-form-actions-confirm')

        //  Should a hidden input be appended?
        const simple = (element.getAttribute('data-form-actions-confirm-simple') !== null)
        const form = element.closest('form')

        // Remove hidden fields before doing anything
        removeFormHiddenField(form)
        form.removeAttribute('target')

        //  Trigger confirm dialog
        if (window.dpconfirm(msg)) {
          //  Add hidden field with intended form action
          if (simple === false) {
            const hiddenInputValue = ev.currentTarget.getAttribute('data-form-actions-confirm-value')
            addFormHiddenField(form, ev.currentTarget.name, hiddenInputValue || 1)
          }
        } else {
          ev.preventDefault()
        }
      })
    })

    /**
     * Add hidden field with sort order of a list of elements
     */
    const manualSortBtn = Array.from(document.querySelectorAll('[data-form-actions-manualsort]'))
    manualSortBtn.forEach(element => {
      element.addEventListener('click', () => {
        const form = element.closest('form')
        const sorted = []
        const target = element.getAttribute('data-form-actions-manualsort')

        // Remove hidden fields before doing anything
        removeFormHiddenField(form)
        form.removeAttribute('target')

        // Loop over set of items to get their actual order
        const sortedArray = Array.from(document.querySelectorAll('input[name="' + target + '"]'))
        sortedArray.forEach(el => {
          sorted.push(el.getAttribute('value'))
        })
        addFormHiddenField(form, 'manualsort', sorted.join())
      })
    })

    /**
     * Add hidden input field when click on export button, and take id for input value.
     */
    const linkPdfExportSingle = Array.from(document.querySelectorAll('[data-form-actions-pdf-single]'))
    linkPdfExportSingle.forEach((element) => {
      element.addEventListener('click', event => {
        event.preventDefault()
        const statementId = event.target.getAttribute('data-form-actions-pdf-single')
        const form = element.closest('form')
        form.setAttribute('target', '_blank')
        removeFormHiddenField(form)
        addFormHiddenField(form, 'pdfExportSingle', statementId)
        form.submit()
      })
    })

    /**
     * Remove hidden fields before doing Filter & remove target from Form.
     */
    const submitFilterSet = Array.from(document.querySelectorAll('[data-submit-filter-set]'))
    if (submitFilterSet.length > 0) {
      submitFilterSet.forEach(el => {
        const form = el.closest('form')
        el.addEventListener('click', () => {
          removeFormHiddenField(form)
          form.removeAttribute('target')
          if (document.querySelector('input[name=r_gdpr_consent]') !== null) {
            document.querySelector('input[name=r_gdpr_consent]').checked = true
          }
        })
      })
    }

    /**
     * Remove hidden fields before Submit marked Statements & remove target from Form.
     */
    const statementReleaseBtn = document.querySelector('button[name=statement_release]')
    if (statementReleaseBtn) {
      const form = statementReleaseBtn.closest('form')
      statementReleaseBtn.addEventListener('click', () => {
        removeFormHiddenField(form)
        form.removeAttribute('target')
      })
    }

    /**
     * Submit institution list form with target="_blank" to open a new tab with pdf view, containing only filtered/selected statements
     * This is as ugly as possible, needs urgently to be refactored, I was not able to to it better within existing constraints
     */
    const formActionsPdfInstitutionsList = Array.from(document.querySelectorAll('[data-form-actions-pdf-institutions-list]'))
    let submitAction

    if (formActionsPdfInstitutionsList.length > 0) {
      formActionsPdfInstitutionsList.forEach((element) => {
        const form = element.closest('form')
        element.addEventListener('click', () => {
          if (document.querySelector('input[name=r_gdpr_consent]') !== null) {
            document.querySelector('input[name=r_gdpr_consent]').checked = true
          }

          submitAction = 'pdfExport'

          // Remove hidden fields before doing anything
          removeFormHiddenField(form)
          form.removeAttribute('target')
          form.addEventListener('submit', exportInstitutionsList)

          function exportInstitutionsList (e) {
            //  Only xecute if form was submitted by data-form-actions-pdf-institutions-list button, not by data-form-actions-pdf-single button
            if (typeof form === 'undefined') {
              return
            }

            if (submitAction === 'pdfExport') {
              this.setAttribute('target', '_blank')
              e.preventDefault()

              //  Perform initial toggle
              toggleToPrint(form, '[name="item_check[]"], [data-checkable-item]')

              addFormHiddenField(form, 'pdfExport', 1)
              this.submit()
              submitAction = ''
              form.removeEventListener('submit', exportInstitutionsList)
              if (document.querySelector('input[name=r_gdpr_consent]') !== null) {
                document.querySelector('input[name=r_gdpr_consent]').checked = false
              }
            } else {
              removeFormHiddenField(form)
              this.removeAttribute('target')
            }
            if (document.querySelector('input[name=r_gdpr_consent]') !== null) {
              document.querySelector('input[name=r_gdpr_consent]').checked = false
            }
          }
        })
      })
    }

    /**
     * Control which submit button will be used when input elements are
     * Hit with a return key
     *
     * Since browsers tend to grab the enter key in forms to submit by
     * Default, this first defines the expected submit action and then
     * Disables any other possible side-effect actions.
     */
    const formActionsSubmitTarget = Array.from(document.querySelectorAll('[data-form-actions-submit-target]'))
    if (formActionsSubmitTarget.length > 0) {
      formActionsSubmitTarget.forEach((element) => {
        const form = element.closest('form')
        element.addEventListener('keypress', e => {
          if (e.keyCode === 13) {
            const target = element.getAttribute('data-form-actions-submit-target')
            document.querySelector(target).click()
            e.preventDefault()
          }
        })
        const otherButtonsInForm = Array.from(form.querySelectorAll('input:not(textarea):not([type=submit])'))
        otherButtonsInForm.forEach(btn => {
          btn.addEventListener('keypress', e => (e.keyCode !== 13))
        })
      })
    }
  })()
}

// Function to set attributes as Object || set multiple attributes
function setAttributes (el, options) {
  Object.keys(options).forEach(attr => {
    el.setAttribute(attr, options[attr])
  })
}

export { addFormHiddenField, removeFormHiddenField, FormActions }
