/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * This file, like AssessmentTableOriginal.js, serves as a quick way to move code that formerly lived in inline scripts
 * inside the webpack scope. These are to be refactored, generalized or made obsolete by migrating
 * their functionality to other components.
 */
import { checkResponse, dpApi } from '@demos-europe/demosplan-ui/src'

export default function AssessmentTable () {
  /*
   * Fix for T6396: if scrolled down the select parent has `position:fixed`
   * and the select instantly closes options dropdown after having opened on click
   */
  $('#sort').on('click', function (e) {
    e.stopPropagation()
  })

  /**
   * Update the filter hash to be used for requests on the assessment table
   * @param {String} procedureId
   * @param {Array} newlySelectedFilters
   */
  window.updateFilterHash = function (procedureId, filterOptions = []) {
    // Get inputfields from assessment table (filters not included)
    let inputFields = $('form[name=bpform]').serializeArray()
    inputFields = inputFields.filter(inputField => inputField.name.includes('filter') === false)

    // Add currently selected filters
    if (filterOptions.length) {
      inputFields = [...inputFields, ...filterOptions]
    }

    // We have to add the selected items from advanced search options because they are out of the form's scope. the reason why is the dpM
    Array.from(document.querySelectorAll("input[name='search_fields[]']")).forEach(el => {
      if (el.checked) {
        inputFields.push({ name: 'search_fields[]', value: el.getAttribute('id') })
      }
    })

    return dpApi({
      method: 'POST',
      data: JSON.stringify(inputFields),
      responseType: 'json',
      url: Routing.generate(
        'dplan_api_procedure_update_filter_hash',
        { procedureId })
    }).then(checkResponse)
      .then((data) => {
        return data.data.attributes.hash
      })
  }

  window.submitForm = function (event, task) {
    // In case the call originated from a native browser event it needs to be terminated
    if (event) {
      event.stopPropagation()
      event.preventDefault()
    }

    const oldTarget = document.bpform.target
    const oldAction = document.bpform.action
    let abfrageBox

    const inputFields = $('form[name=bpform]').serializeArray()
    const filterOptions = inputFields.filter(inputField => inputField.name.includes('filter') && inputField.value !== '')

    const procedureId = $('form[name=bpform]').data('statement-admin-container')
    window.updateFilterHash(procedureId, filterOptions)
      .then((filterHash) => {
        document.bpform.action = Routing.generate('dplan_assessmenttable_view_table', {
          procedureId,
          filterHash
        })

        let formsend = false

        switch (task) {
          case 'delete':
            abfrageBox = dpconfirm(Translator.trans('check.entries.marked.delete'))
            if (abfrageBox === true) {
              document.getElementsByName('r_action')[0].value = 'delete'
              document.bpform.submit()
              formsend = true
            } else {
              return false
            }
            break

          case 'copy':
            // Copying of statements within one procedure is not handled in copyStatementModal yet
            abfrageBox = dpconfirm(Translator.trans('check.entries.marked.copy'))
            if (abfrageBox === true) {
              document.getElementsByName('r_action')[0].value = 'copy'
              document.bpform.submit()
              formsend = true
            } else {
              return false
            }
            break

          case 'search':
          case 'filters':
          case 'viewMode':
            document.bpform.submit()
            formsend = true
            break
        }

        /*
         * If we want to send send the form, don'T reset those values
         * otherwise it could be that it happens too fast so we send the wrong data.
         */
        if (formsend === false) {
          document.bpform.target = oldTarget
          document.bpform.action = oldAction
        }
      })
  }
}
