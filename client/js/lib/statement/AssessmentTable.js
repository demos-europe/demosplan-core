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
import { checkResponse, dpApi } from '@demos-europe/demosplan-ui'

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
      url: Routing.generate('dplan_api_procedure_update_filter_hash', { procedureId }),
      data: inputFields
    }).then(checkResponse)
      .then((data) => {
        return data.data.attributes.hash
      })
  }

  window.submitForm = function (event, task, filterHash = null) {
    // In case the call originated from a native browser event it needs to be terminated
    if (event) {
      event.stopPropagation()
      event.preventDefault()
    }

    const inputFields = $('form[name=bpform]').serializeArray()
    const filterOptions = inputFields.filter(inputField => inputField.name.includes('filter') && inputField.value !== '')
    const procedureId = $('form[name=bpform]').data('statement-admin-container')

    if (filterHash) {
      // there are cases were the filterHash has not to be updated, but take the current one
      document.bpform.action = Routing.generate('dplan_assessmenttable_view_table', { procedureId, filterHash })
      handleFormSubmission(task)
    } else {
      window.updateFilterHash(procedureId, filterOptions)
        .then((filterHash) => {
          document.bpform.action = Routing.generate('dplan_assessmenttable_view_table', { procedureId, filterHash })
          handleFormSubmission(task)
        })
    }
  }

  function handleFormSubmission (task) {
    const oldTarget = document.bpform.target
    const oldAction = document.bpform.action
    let formsend = false

    switch (task) {
      case 'delete':
        if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
          document.getElementsByName('r_action')[0].value = 'delete'
          document.bpform.submit()
          formsend = true
        } else {
          return false
        }
        break

      case 'copy':
        // Copying of statements within one procedure is not handled in copyStatementModal yet
        if (dpconfirm(Translator.trans('check.entries.marked.copy'))) {
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
     * if we want to submit the form, don't reset those values,
     * otherwise, it might happen too quickly, causing incorrect data to be sent
     */
    if (formsend === false) {
      document.bpform.target = oldTarget
      document.bpform.action = oldAction
    }
  }
}
