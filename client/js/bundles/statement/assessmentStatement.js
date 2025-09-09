/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for assessment_statement.html.twig and cluster_detail.html.twig
 */

import { DpEditor, DpLabel, DpUploadFiles, dpValidate, hasPermission } from '@demos-europe/demosplan-ui'
import AssessmentStatement from '@DpJs/lib/statement/AssessmentStatement'
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DetailView from '@DpJs/components/statement/assessmentTable/DetailView/DetailView'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'
import StatementStore from '@DpJs/store/statement/Statement'
import VoterStore from '@DpJs/store/statement/Voter'

const stores = {
  AssessmentTable: AssessmentTableStore,
  Statement: StatementStore,
  Voter: VoterStore
}

const components = {
  DetailView,
  DpEditor,
  DpLabel,
  DpUploadFiles
}

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
  components.DpBoilerPlateModal = DpBoilerPlateModal
}

initialize(components, stores).then(() => {
  dpValidate()
  AssessmentStatement()
})
