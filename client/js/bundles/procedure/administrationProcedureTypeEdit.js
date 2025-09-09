/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_procedure_type_edit.html.twig
 */

/*
 * import DpAccordion from '@DpJs/components/core/DpAccordion'
 * import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
 */
import { defineAsyncComponent } from 'vue'
import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import ProcedureTypeSelect from '@DpJs/components/procedure/admin/ProcedureTypeSelect'

const components = {
  DpEditor: defineAsyncComponent(async () => {
    const { DpEditor } = await import('@demos-europe/demosplan-ui')
    return DpEditor
  }),
  ProcedureTypeSelect
}
// Const components = { DpAccordion, DpEditor }
const stores = {}
const apiStores = []

initialize(components, stores, apiStores).then(() => {
  dpValidate()

  const toggleCheckboxesExclusively = (array) => {
    array.forEach(el => {
      el.addEventListener('change', e => {
        if (e.target.checked === true) {
          const notClickedElements = array.filter(el => el !== e.target)
          notClickedElements.forEach(el => {
            el.checked = false
          })
        }
      })
    })
  }

  /*
   * Collect fields as groups that cant be selected at the same time
   * @see T19598
   */
  const conditionalGroups = []

  // Map reference checkboxes
  conditionalGroups.push(document.querySelectorAll('[data-name=mapAndCountyReference_enabled], [data-name=countyReference_enabled]'))
  // Feedback checkboxes
  conditionalGroups.push(document.querySelectorAll('[data-name=getEvaluationMailViaEmail_enabled], [data-name=getEvaluationMailViaSnailMailOrEmail_enabled]'))
  // Street checkboxes
  conditionalGroups.push(document.querySelectorAll('[data-name=street_enabled], [data-name=streetAndHouseNumber_enabled]'))
  // Mail checkboxes
  conditionalGroups.push(document.querySelectorAll('[data-name=emailAddress_enabled], [data-name=phoneOrEmail_enabled]'))
  // Phone checkboxes
  conditionalGroups.push(document.querySelectorAll('[data-name=phoneNumber_enabled], [data-name=phoneOrEmail_enabled]'))

  conditionalGroups.forEach(group => {
    toggleCheckboxesExclusively(Array.from(group))
  })
})
