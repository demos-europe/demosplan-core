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
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DetailView from '@DpJs/components/statement/assessmentTable/DetailView/DetailView'
import { DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'
import { dpValidate } from '@demos-europe/demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatementStore from '@DpJs/store/statement/Statement'
import VoterStore from '@DpJs/store/statement/Voter'

const stores = {
  assessmentTable: AssessmentTableStore,
  boilerplates: BoilerplatesStore,
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
