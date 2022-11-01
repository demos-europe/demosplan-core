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

import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import DpBulkEditStatement from '@DpJs/components/statement/assessmentTable/DpBulkEditStatement'
import FragmentStore from '@DpJs/store/statement/Fragment'
import { initialize } from '@DpJs/InitVue'
import StatementStore from '@DpJs/store/statement/Statement'

const stores = {
  statement: StatementStore,
  fragment: FragmentStore,
  assessmentTable: AssessmentTableStore
}

const components = {
  DpBulkEditStatement
}

initialize(components, stores)
