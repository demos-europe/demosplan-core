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

import {
  DpAccordion,
  DpButton,
  DpDatepicker,
  DpEditor,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpSelect,
  DpUploadFiles,
} from '@demos-europe/demosplan-ui'
import AssessmentStatement from '@DpJs/lib/statement/AssessmentStatement'
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import DpAutofillSubmitterData from '@DpJs/components/statement/statement/DpAutofillSubmitterData'
import DpNewStatement from '@DpJs/components/assessmenttable/DpNewStatement'
import DpSelectStatementCluster from '@DpJs/components/statement/statement/SelectStatementCluster'
import { initialize } from '@DpJs/InitVue'
import StatementPublish from '@DpJs/components/statement/statement/StatementPublish'
import StatementStore from '@DpJs/store/statement/Statement'
import StatementVoter from '@DpJs/components/statement/voter/StatementVoter'
import VoterStore from '@DpJs/store/statement/Voter'

const stores = {
  AssessmentTable: AssessmentTableStore,
  Statement: StatementStore,
  Voter: VoterStore,
}

const components = {
  DpNewStatement,
  DpAccordion,
  DpAutofillSubmitterData,
  DpButton,
  DpDatepicker,
  DpEditor,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpSelect,
  DpSelectStatementCluster,
  DpUploadFiles,
  StatementPublish,
  StatementVoter,
}

initialize(components, stores).then(() => {
  AssessmentStatement()
})
