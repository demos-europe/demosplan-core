/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for assessment_statement.html.twig and cluster_detail.html.twig
 */

import AssessmentStatement from '@DpJs/lib/statement/AssessmentStatement'
import AssessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import DetailView from '@DpJs/components/statement/assessmentTable/DetailView/DetailView'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatementStore from '@DemosPlanStatementBundle/store/Statement'
import VoterStore from '@DemosPlanStatementBundle/store/Voter'

const stores = {
  assessmentTable: AssessmentTableStore,
  statement: StatementStore,
  voter: VoterStore
}

const components = {
  DetailView,
  DpUploadFiles
}

initialize(components, stores).then(() => {
  dpValidate()
  AssessmentStatement()
})
