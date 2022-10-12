/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for fragment_statement.html.twig
 */

import AssessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import DeleteFragmentButton from './../lib/DeleteFragmentButton'
import DpCreateStatementFragment from './../components/statement/DpCreateStatementFragment'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const stores = {
  assessmentTable: AssessmentTableStore
}
const components = {
  DpCreateStatementFragment
}

initialize(components, stores).then(() => {
  DeleteFragmentButton()

  // To Disable Submit Button after Form Validate
  dpValidate()
  const fragmentSubmitButton = document.getElementById('fragmentSubmitButton')
  document.addEventListener('customValidationPassed', function (e) {
    fragmentSubmitButton.disabled = true
  })
})
