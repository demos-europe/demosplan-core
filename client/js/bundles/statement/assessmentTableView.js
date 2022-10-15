/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for assessment_table_view.html.twig
 */

import AssessmentTable from '@DemosPlanStatementBundle/lib/AssessmentTable'
import AssessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import AssessmentTableToc from '@DemosPlanStatementBundle/components/assessmentTable/TocView/AssessmentTableToc'
import DpBulkEditFragment from '@DemosPlanStatementBundle/components/assessmentTable/DpBulkEditFragment'
import DpBulkEditStatement from '@DemosPlanStatementBundle/components/assessmentTable/DpBulkEditStatement'
import DpTable from '@DemosPlanStatementBundle/components/assessmentTable/DpTable'
import FilterStore from '@DemosPlanStatementBundle/store/Filter'
import FragmentStore from '@DemosPlanStatementBundle/store/Fragment'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatementStore from '@DemosPlanStatementBundle/store/Statement'

const stores = {
  AssessmentTableStore,
  FilterStore,
  FragmentStore,
  StatementStore
}

const components = {
  AssessmentTableToc,
  DpBulkEditFragment,
  DpBulkEditStatement,
  DpTable
}

initialize(components, stores).then(AssessmentTable())
