/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for assessment_table_original_view.html.twig
 */

import AssessmentTableOriginal from '@DpJs/lib/statement/AssessmentTableOriginal'
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import { DpButton } from '@demos-europe/demosplan-ui'
import DpFilterModal from '@DpJs/components/statement/assessmentTable/DpFilterModal'
import FilterStore from '@DpJs/store/statement/Filter'
import { initialize } from '@DpJs/InitVue'
import OriginalStatementsTable from '@DpJs/components/statement/originalStatementsTable/OriginalStatementsTable'
import SearchModal from '@DpJs/components/statement/assessmentTable/SearchModal/SearchModal'
import StatementStore from '@DpJs/store/statement/Statement'

const stores = {
  AssessmentTable: AssessmentTableStore,
  Filter: FilterStore,
  Statement: StatementStore
}

const components = {
  DpButton,
  DpFilterModal,
  OriginalStatementsTable,
  SearchModal
}

initialize(components, stores).then(() => {
  AssessmentTableOriginal()
})
