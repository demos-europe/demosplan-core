/**
 * (c) 2010-present DEMOS plan GmbH.
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
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBulkEditStatement from '@DpJs/components/statement/assessmentTable/DpBulkEditStatement'
import FragmentStore from '@DpJs/store/statement/Fragment'
import { hasPermission } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import StatementStore from '@DpJs/store/statement/Statement'

const stores = {
  AssessmentTable: AssessmentTableStore,
  Fragment: FragmentStore,
  Statement: StatementStore
}

const components = {
  DpBulkEditStatement
}

if (hasPermission('area_admin_boilerplates')) {
  stores.Boilerplates = BoilerplatesStore
}

initialize(components, stores)
