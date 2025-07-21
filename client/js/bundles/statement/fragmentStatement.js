/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for fragment_statement.html.twig
 */

import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import DeleteFragmentButton from '@DpJs/lib/statement/DeleteFragmentButton'
import DpCreateStatementFragment from '@DpJs/components/statement/statement/DpCreateStatementFragment'
import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

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
