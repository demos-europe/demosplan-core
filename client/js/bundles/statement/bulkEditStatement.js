/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for bulk_edit_statement.html.twig
 */

import AssessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import DpBulkEditStatement from '@DpJs/components/statement/assessmentTable/DpBulkEditStatement'
import FragmentStore from '@DemosPlanStatementBundle/store/Fragment'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatementStore from '@DemosPlanStatementBundle/store/Statement'

const stores = {
  statement: StatementStore,
  fragment: FragmentStore,
  assessmentTable: AssessmentTableStore
}

const components = {
  DpBulkEditStatement
}

initialize(components, stores)
