/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for assessment_table_new_statement.html.twig
 */

import AssessmentStatement from '@DpJs/lib/statement/AssessmentStatement'
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import DpNewStatement from '@DpJs/components/assessmenttable/DpNewStatement'
import { DpUploadFiles } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import StatementStore from '@DpJs/store/statement/Statement'
import VoterStore from '@DpJs/store/statement/Voter'

const stores = {
  AssessmentTable: AssessmentTableStore,
  Statement: StatementStore,
  Voter: VoterStore
}

const components = {
  DpNewStatement,
  DpUploadFiles
}

initialize(components, stores).then(() => {
  AssessmentStatement()
})
