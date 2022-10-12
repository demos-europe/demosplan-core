/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for assessment_table_new_statement.html.twig
 */

import AssessmentStatement from '@DemosPlanStatementBundle/lib/AssessmentStatement'
import AssessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import DpNewStatement from './../components/DpNewStatement'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatementStore from '@DemosPlanStatementBundle/store/Statement'
import VoterStore from '@DemosPlanStatementBundle/store/Voter'

const stores = {
  assessmentTable: AssessmentTableStore,
  statement: StatementStore,
  voter: VoterStore
}

const components = {
  DpNewStatement,
  DpUploadFiles
}

initialize(components, stores).then(() => {
  AssessmentStatement()
})
