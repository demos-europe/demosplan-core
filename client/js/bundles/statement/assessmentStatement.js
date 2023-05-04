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

import { DpEditor, DpLabel, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import AssessmentStatement from '@DpJs/lib/statement/AssessmentStatement'
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DetailView from '@DpJs/components/statement/assessmentTable/DetailView/DetailView'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'
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
  DpBoilerPlateModal,
  DpEditor,
  DpLabel,
  DpUploadFiles
}

initialize(components, stores).then(() => {
  dpValidate()
  AssessmentStatement()
})
