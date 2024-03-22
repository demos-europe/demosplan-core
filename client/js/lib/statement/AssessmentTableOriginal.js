/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * This file, like AssessmentTable.js, serves as a quick way to move code that formerly lived in inline scripts
 * inside the webpack scope. These are to be refactored, generalized or made obsolete by migrating
 * their functionality to other components.
 */

import { checkResponse, dpApi } from '@demos-europe/demosplan-ui'
import { scrollTo } from 'vue-scrollto'

export default function AssessmentTableOriginal () {
  /**
   * Update the filter hash to be used fo requests on the assessment table
   * @param procedureId
   * @param newlySelectedFilters
   */
  window.updateFilterHash = function (procedureId, filterOptions = []) {
    // Get inputfields from original table (filters not included)
    let inputFields = $('form[name=bpform]').serializeArray()
    inputFields = inputFields.filter(inputField => inputField.name.includes('filter') === false)

    // Add currently selected filters
    if (filterOptions.length) {
      inputFields = inputFields.concat(filterOptions)
    }

    return dpApi({
      method: 'POST',
      url: Routing.generate('dplan_api_procedure_update_original_filter_hash', { procedureId }),
      data: inputFields
    }).then(checkResponse)
      .then(function (data) {
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

    const procedureId = $('form[name=bpform]').data('assessment-original-statements')
    window.updateFilterHash(procedureId, filterOptions)
      .then((filterHash) => {
        document.bpform.action = Routing.generate('dplan_assessmenttable_view_original_table', {
          procedureId,
          filterHash
        })

        switch (task) {
          case 'copy':
            abfrageBox = dpconfirm(Translator.trans('check.entries.marked.copy'))
            if (abfrageBox === true) {
              document.bpform.r_action.value = 'copy'
              document.bpform.submit()
            }
            break

          case 'search':
          case 'filters':
            document.bpform.submit()
            break
        }

        document.bpform.target = oldTarget
        document.bpform.action = oldAction
      })
  }

  // Scroll to Element
  if (window.location.hash) {
    let hash = window.location.hash
    const hasQuestionMark = window.location.hash.includes('?')

    if (hasQuestionMark) {
      hash = hash.split('?')[0]
    }
    scrollTo(hash, { offset: -180 })
  }
}
