/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for assessment_table_original_view.html.twig
 */

import AssessmentTableOriginal from '@DemosPlanStatementBundle/lib/AssessmentTableOriginal'
import AssessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import { DpButton } from 'demosplan-ui/components'
import DpFilterModal from '@DemosPlanStatementBundle/components/assessmentTable/DpFilterModal'
import FilterStore from '@DemosPlanStatementBundle/store/Filter'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import OriginalStatementsTable from '@DemosPlanStatementBundle/components/originalStatementsTable/OriginalStatementsTable'
import SearchModal from '@DemosPlanStatementBundle/components/assessmentTable/SearchModal/SearchModal'
import StatementStore from '@DemosPlanStatementBundle/store/Statement'

const stores = {
  AssessmentTable: AssessmentTableStore,
  filter: FilterStore,
  statement: StatementStore
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
